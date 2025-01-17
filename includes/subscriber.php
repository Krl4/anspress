<?php
/**
 * AnsPress subscribe and subscriber related functions
 *
 * @package   AnsPress
 * @author    Rahul Aryan <admin@rahularyan.com>
 * @license   GPL-2.0+
 * @link      http://anspress.io
 * @copyright 2014 Rahul Aryan
 */

/**
 * Insert new subscriber.
 * @param  integer $user_id User id.
 * @param  integer $item_id Item id i.e. post ID, term ID etc..
 * @param  string  $actiity Activity name.
 * @return false|integer
 */
function ap_new_subscriber( $user_id, $item_id, $actiity, $question_id = 0 ) {
	global $wpdb;

	// Bail if user_id or item_id is 0.
	if ( 0 == $user_id || 0 == $item_id ) {
		return false;
	}

	$row = $wpdb->insert(
		$wpdb->ap_subscribers,
		array(
			'user_id' => $user_id,
			'question_id' => $question_id,
			'item_id' => $item_id,
			'activity' => $actiity,
		),
		array(
			'%d',
			'%d',
			'%d',
			'%s',
		)
	);

	if ( false !== $row ) {
		do_action( 'ap_new_subscriber', $user_id, $question_id, $item_id, $actiity );
		return $wpdb->insert_id;
	}

	return $row;
}


/**
 * Remove subscriber for question or term
 * @param  integer         $item_id  	Question ID or Term ID
 * @param  integer         $user_id    	WP user ID
 * @param  string          $activity    Any sub ID
 * @param  boolean|integer $sub_id      @deprecated Type of subscriber, empty string for question
 * @return bollean|integer
 */
function ap_remove_subscriber($item_id, $user_id = false, $activity = false, $sub_id = false) {
	if ( false !== $sub_id ) {
		_deprecated_argument( __FUNCTION__, '3.0', '$sub_id argument deprecated since 2.4' );
	}

	global $wpdb;

	$cols = array( 'item_id' => (int) $item_id );

	if ( false !== $user_id ) {
		$cols['user_id'] = (int) $user_id;
	}

	if ( false !== $activity ) {
		$cols['activity'] = sanitize_title_for_query( $activity );
	}

	$row = $wpdb->delete(
		$wpdb->ap_subscribers,
		$cols,
		array( '%d', '%d', '%s' )
	);

	if ( false === $row ) {
		return false;
	}

	do_action( 'ap_removed_subscriber', $user_id, $item_id, $activity );

	return $row;
}

/**
 * Check if user is subscribed to question or term
 * @param  integer        $item_id 		Item id.
 * @param  integer        $activity 	Activity name.
 * @param  string|boolean $user_id 		User id.
 * @return boolean
 */
function ap_is_user_subscribed($item_id, $activity, $user_id = false) {

	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( $user_id === false ) {
		$user_id = get_current_user_id();
	}

	global $wpdb;

	$key = $item_id .'::'. $activity .'::'. $user_id;

	$cache = wp_cache_get( $key, 'ap_subscriber_count' );

	if ( false !== $cache ) {
		return $cache > 0;
	}

	$count = $wpdb->get_var( $wpdb->prepare( 'SELECT count(*) FROM '. $wpdb->ap_subscribers .' WHERE item_id=%d AND activity="%s" AND user_id = %d', $item_id, $activity, $user_id ) );

	wp_cache_set( $key, $count, 'ap_subscriber_count' );

	return $count > 0;
}

/**
 * Return the count of subscribers for question or term
 * @param  integer $item_id 	Item id.
 * @param  string  $activity 	Type of subscription.
 * @return integer
 */
function ap_subscribers_count($item_id = false, $activity = 'q_all') {
	global $wpdb;

	$item_id = $item_id ? $item_id : get_question_id();

	$key = $item_id.'_'.$activity;

	$cache = wp_cache_get( $key, 'ap_subscriber_count' );

	if ( false !== $cache ) {
		return $cache;
	}

	$count = $wpdb->get_var( $wpdb->prepare( 'SELECT count(*) FROM '. $wpdb->ap_subscribers .' WHERE item_id=%d AND activity="%s"', $item_id, $activity ) );

	wp_cache_set( $key, $count, 'ap_subscriber_count' );

	if ( ! $count ) {
		return 0;
	}

	return $count;
}

/**
 * Get question subscribers count from post meta.
 * @param  intgere|object $question Question object.
 * @return integer
 */
function ap_question_subscriber_count( $question ) {
	if ( ! is_object( $question ) || ! isset( $question->post_type ) ) {
		$question = get_post( $question );
	}

	// Return if not question.
	if ( 'question' != $question->post_type ) {
		return 0;
	}

	return (int) get_post_meta( $question->ID, ANSPRESS_SUBSCRIBER_META, true );
}

/**
 * Return subscriber count in human readable format
 * @return string
 * @since 2.0.0-alpha2
 */
function ap_subscriber_count_html($post = false) {

	if ( ! $post ) {
		global $post;
	}

	$subscribed = ap_is_user_subscribed( $post->ID, 'q_all' );
	$total_subscribers = ap_subscribers_count( $post->ID );

	if ( $total_subscribers == '1' && $subscribed ) {
		return __( 'Only you are subscribed to this question.', 'ap' ); } elseif ($subscribed)
		return sprintf( __( 'You and <strong>%s people</strong> subscribed to this question.', 'ap' ), ($total_subscribers -1) );
	elseif ($total_subscribers == 0)
		return __( 'No one is subscribed to this question.', 'ap' );
	else {
		return sprintf( __( '<strong>%d people</strong> subscribed to this question.', 'ap' ), $total_subscribers ); }
}

/**
 * Return all subscribers of a question
 * @param  integer $action_id  Item id.
 * @param  string  $activity   Subscribe activity.
 * @return array
 * @since  2.1
 */
function ap_get_subscribers( $action_id, $activity = 'q_all', $limit = 10 ) {
	global $wpdb;

	$key = $action_id.'_'.$activity;

	$cache = wp_cache_get( $key, 'ap_subscribers' );

	if ( false !== $cache ) {
		return $cache;
	}

	$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM '.$wpdb->ap_subscribers.' where item_id=%d AND activity="%s" LIMIT 0 , %d', $action_id, $activity, $limit ) );

	// Set individual cache for subscriber.
	if ( $results ) {
		foreach ( $results as $s ) {
			$s_key = $s->item_id .'_'. $s->activity .'_'. $s->user_id;
			$old_cache = wp_cache_get( $s_key, 'ap_subscribers' );

			if ( false !== $old_cache ) {
				wp_cache_set( $s_key, $s, 'ap_subscribers' );
			}
		}
	}

	return $results;
}


/**
 * Output subscribe btn HTML
 * @param 	boolean|integer $action_id  Question ID or Term ID.
 * @param 	string|false    $type       Subscribe type.
 * @since 	2.0.1
 */
function ap_subscribe_btn_html($action_id = false, $type = false) {

	global $question_category, $question_tag;

	if ( false === $action_id ) {
		if ( is_question() ) {
			$action_id = get_question_id();
		} elseif (is_question_category())
			$action_id = $question_category->term_id;
	} elseif ( is_question_tag() ) {
		$action_id = $question_tag->term_id;
	}

	$subscribe_type = 'q_all';

	if ( $type == false ) {
		$subscribe_type = apply_filters( 'ap_subscribe_btn_type', 'q_all' );
	} elseif ( $type === 'category' || $type === 'tag' ) {
		$subscribe_type = 'tax_new_q';
	}

	$subscribed = ap_is_user_subscribed( $action_id, $subscribe_type );

	$nonce = wp_create_nonce( 'subscribe_'.$action_id.'_'.$subscribe_type );

	$title = ( ! $subscribed) ? __( 'Follow', 'ap' ) : __( 'Unfollow', 'ap' );
	?>
	<div class="ap-subscribe-btn" id="<?php echo 'subscribe_'.$action_id; ?>">
		<a href="#" class="ap-btn<?php echo ($subscribed) ? ' active' :''; ?>" data-query="<?php echo 'subscribe::'. $nonce .'::'. $action_id .'::'. $subscribe_type; ?>" data-action="ajax_btn" data-cb="apSubscribeBtnCB">
            <?php echo ap_icon( 'rss', true ); ?> <span class="text"><?php echo $title ?></span>      
        </a>
        <b class="ap-btn-counter" data-view="<?php echo 'subscribe_'.$action_id; ?>"><?php echo ap_subscribers_count( $action_id, $subscribe_type ) ?></b>
    </div>

	<?php
}

function ap_question_subscribers($action_id = false, $type = '', $avatar_size = 30) {
	global $question_category, $question_tag;

	if ( false === $action_id ) {
		if ( is_question() ) {
			$action_id = get_question_id();
		} elseif ( is_question_category() ) {
			$action_id = $question_category->term_id;
		} elseif ( is_question_tag() ) {
			$action_id = $question_tag->term_id;
		}
	}

	if ( $type == '' ) {
		$type = is_question() ? 'q_all' : 'tax_new_q' ;
	}

	$subscribers = ap_get_subscribers( $action_id, $type );

	if ( $subscribers ) {
		echo '<div class="ap-question-subscribers clearfix">';
		echo '<div class="ap-question-subscribers-inner">';
		foreach ( $subscribers as $subscriber ) {
			echo '<a href="'.ap_user_link( $subscriber->user_id ).'"';
			ap_hover_card_attributes( $subscriber->user_id );
			echo '>'.get_avatar( $subscriber->user_id, $avatar_size ).'</a>';
		}
		echo '</div>';
		echo '</div>';
	}
}

/**
 * Subscribe a user for a question.
 */
function ap_subscribe_question( $posta, $user_id = false ) {
	if ( ! is_object( $posta ) || ! isset( $posta->post_type ) ) {
		$posta = get_post( $posta );
	}

	// Return if not question.
	if ( 'question' != $posta->post_type ) {
		return false;
	}

	if ( false === $user_id ) {
		$user_id = $posta->post_author;
	}

	if ( ! ap_is_user_subscribed( $posta->ID, 'q_all', $user_id ) ) {
		ap_new_subscriber( $user_id, $posta->ID, 'q_all', $posta->ID );
	}
}

/**
 * Return all subscribers id
 * @param  integer      $item_id Item id.
 * @param  string|array $activity Activity type.
 * @return array   Ids of subscribed user.
 */
function ap_subscriber_ids( $item_id =false, $activity = 'q_all', $question_id = 0 ) {
	global $wpdb;

	$key = $item_id . '::' . $activity .'::'. $question_id;

	$cache = wp_cache_get( $key, 'ap_subscribers_ids' );

	if ( false !== $cache ) {
		return $cache;
	}

	$item = '';

	if ( false !== $item_id ) {
		$item = $wpdb->prepare( 'item_id = %d AND', $item_id );
	}

	$question = '';
	if ( 0 != $question_id ) {
		$question = $wpdb->prepare( 'AND question_id=%d', $question_id );
	}

	$i = 1;
	if ( is_array( $activity ) && count( $activity ) > 0 ) {
		$activity_q .= ' activity IN(';

		foreach ( $activity as $a ) {
			$activity_q .= $wpdb->prepare( "'%s'", $activity );
			if ( $i != count( $activity ) ) {
				$activity_q .= ', ';
			}
			$i++;
		}

		$activity_q .= ') ';
	} else {
		$activity_q = $wpdb->prepare( " activity = '%s' ", $activity );
	}

	$results = $wpdb->get_col( "SELECT user_id FROM $wpdb->ap_subscribers WHERE $item $activity_q $question GROUP BY user_id" );

	wp_cache_set( $key, $results, 'ap_subscribers_ids' );
	return $results;
}

/**
 * Remove current user id from subscribers id
 * @param  array $subscribers Subscribers user_id.
 * @return array
 */
function ap_unset_current_user_from_subscribers($subscribers) {
	// Remove current user from subscribers.
	if ( ! empty( $subscribers ) && ($key = array_search( get_current_user_id(), $subscribers )) !== false ) {
	    unset( $subscribers[$key] );
	}

	return $subscribers;
}
