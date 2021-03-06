<?php

namespace Drupal\activity_viewer\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;

/**
 * Filters activity for a personalised homepage.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("activity_filter_personalised_homepage")
 */
class ActivityFilterPersonalisedHomepage extends FilterPluginBase {

  /**
   * Not exposable.
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * Filters out activity items the user is not allowed to see.
   *
   * The access to the activity items may be limited by the following:
   *  1. Value in field_visibility_value on a Post entity.
   *  2. Node access grants (this includes the field_node_visibility_value and
   *     the nodes in a closed group)
   *  3. The comment or post is posted in a (closed) group.
   *
   * In addition to the condition used in this filter there may be some other
   * filters active in the given view (e.g. destination).
   *
   * Probably want to extend this to entity access based on the node grant
   * system when this is implemented.
   * See https://www.drupal.org/node/777578
   */
  public function query() {
    $account = $this->view->getUser();
    $group_memberships = social_group_get_all_group_members($account->id());

    // Add tables and joins.
    $this->query->addTable('activity__field_activity_recipient_group');
    $this->query->addTable('activity__field_activity_entity');

    $configuration = [
      'left_table' => 'activity__field_activity_entity',
      'left_field' => 'field_activity_entity_target_id',
      'table' => 'post_field_data',
      'field' => 'id',
      'operator' => '=',
      'extra' => [
        0 => [
          'left_field' => 'field_activity_entity_target_type',
          'value' => 'post',
        ],
      ],
    ];
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $this->query->addRelationship('post', $join, 'activity__field_activity_entity');

    $configuration = [
      'left_table' => 'post',
      'left_field' => 'id',
      'table' => 'post__field_visibility',
      'field' => 'entity_id',
      'operator' => '=',
    ];
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $this->query->addRelationship('post__field_visibility', $join, 'post__field_visibility');

    // Join node table(s).
    $configuration = [
      'left_table' => 'activity__field_activity_entity',
      'left_field' => 'field_activity_entity_target_id',
      'table' => 'node_access',
      'field' => 'nid',
      'operator' => '=',
      'extra' => [
        0 => [
          'left_field' => 'field_activity_entity_target_type',
          'value' => 'node',
        ],
      ],
    ];
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $this->query->addRelationship('node_access', $join, 'node_access_relationship');

    if ($account->isAnonymous()) {
      $configuration['table'] = 'node_field_data';
      $join = Views::pluginManager('join')->createInstance('standard', $configuration);
      $this->query->addRelationship('node_field_data', $join, 'node_field_data');
    }

    // Add queries.
    $and_wrapper = db_and();
    $or = db_or();

    // Nodes: retrieve all the nodes 'created' activity by node access grants.
    $node_access = db_and();
    $node_access->condition('activity__field_activity_entity.field_activity_entity_target_type', 'node', '=');
    $node_access_grants = node_access_grants('view', $account);
    $grants = db_or();
    foreach ($node_access_grants as $realm => $gids) {
      if (!empty($gids)) {
        $and = db_and();

        if ($account->isAnonymous() && strpos($realm, 'field_content_visibility_community') !== FALSE) {
          $and->condition('node_field_data.uid', 0, '!=');
        }

        $grants->condition($and
          ->condition('node_access.gid', $gids, 'IN')
          ->condition('node_access.realm', $realm)
        );
      }
    }
    $node_access->condition($grants);
    // Get all nodes not posted in groups and in groups of user only.
    if ($account->isAuthenticated() && count($group_memberships) > 0) {
      $na_or = db_or();
      $node_access->condition($na_or
        ->isNull('activity__field_activity_recipient_group.field_activity_recipient_group_target_id')
        ->condition('activity__field_activity_recipient_group.field_activity_recipient_group_target_id', $group_memberships, 'IN')
      );
    }
    else {
      $node_access->isNull('activity__field_activity_recipient_group.field_activity_recipient_group_target_id');
    }
    $or->condition($node_access);

    // Posts: retrieve all the posts in groups the user is a member of.
    if ($account->isAuthenticated() && count($group_memberships) > 0) {
      $posts_in_groups = db_and();
      $posts_in_groups->condition('activity__field_activity_entity.field_activity_entity_target_type', 'post', '=');
      $posts_in_groups->condition('activity__field_activity_recipient_group.field_activity_recipient_group_target_id', $group_memberships, 'IN');

      $or->condition($posts_in_groups);
    }

    // Posts: all the posts the user has access to and posted to community.
    $post_access = db_and();
    $post_access->condition('activity__field_activity_entity.field_activity_entity_target_type', 'post', '=');
    $post_access->condition('post__field_visibility.field_visibility_value', '3', '!=');

    if (!$account->hasPermission('view public posts')) {
      $post_access->condition('post__field_visibility.field_visibility_value', '1', '!=');
    }
    if (!$account->hasPermission('view community posts')) {
      $post_access->condition('post__field_visibility.field_visibility_value', '2', '!=');
      // Also do not show recipient posts (e.g. on open groups).
      $post_access->condition('post__field_visibility.field_visibility_value', '0', '!=');
    }
    $post_access->isNull('activity__field_activity_recipient_group.field_activity_recipient_group_target_id');

    $or->condition($post_access);

    $post_status = db_or();
    $post_status->condition('post.status', 1, '=');
    $post_status->condition('activity__field_activity_entity.field_activity_entity_target_type', 'post', '!=');
    $and_wrapper->condition($post_status);

    // Comments: retrieve comments the user has access to.
    if ($account->hasPermission('access comments')) {
      // For comments in groups, the user must be a member of at least 1 group.
      if (count($group_memberships) > 0) {
        $comments_on_content_in_groups = db_and();
        $comments_on_content_in_groups->condition('activity__field_activity_entity.field_activity_entity_target_type', 'comment', '=');
        $comments_on_content_in_groups->condition('activity__field_activity_recipient_group.field_activity_recipient_group_target_id', $group_memberships, 'IN');
        $or->condition($comments_on_content_in_groups);
      }

      $comments_on_content = db_and();
      $comments_on_content->condition('activity__field_activity_entity.field_activity_entity_target_type', 'comment', '=');
      $comments_on_content->isNull('activity__field_activity_recipient_group.field_activity_recipient_group_target_id');
      $or->condition($comments_on_content);
    }

    // Lets add all the or conditions to the Views query.
    $and_wrapper->condition($or);
    $this->query->addWhere('visibility', $and_wrapper);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    $contexts[] = 'user';

    return $contexts;
  }

}
