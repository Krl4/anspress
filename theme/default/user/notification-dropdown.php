<?php
/**
 * Display AnsPress user notification
 *
 * @link http://anspress.io
 * @since 2.3
 *
 * @package AnsPress
 */

?>

<?php if(!ap_opt('notification_sidebar')): ?>
	<div id="ap-notification-dropdown" class="ap-dropdown-menu ap-user-dropdown-menu ap-notification-items">
<?php else: ?>
	<div id="ap-notifiside" class="ap-notification-items">
<?php endif; ?>

	<div class="ap-notification-head clearfix">
		<b><?php _e('Notifications', 'ap'); ?></b>
		<a href="#" data-action="ap_markread_notification" data-query="ap_ajax_action=markread_notification&__nonce='.wp_create_nonce( 'ap_markread_notification_'.get_current_user_id() ).'"><?php _e('Mark all as read', 'ap'); ?></a>
	</div>
	<?php if(ap_has_activities()): ?>
		<div class="ap-notification-items clearfix">
			<div class="ap-notification-scroll scrollbar-dynamic">
				<?php while ( ap_activities() ) : ap_the_activity(); ?>
					<div class="ap-notification-<?php ap_activity_the_id(); ?> ap-notification-item clearfix<?php echo ap_notification_is_unread() ? ' unread' : ''; ?>">
						
						<div class="ap-avatar">
							<a href="<?php echo ap_user_link( ap_activity_user_id() ); ?>"><?php echo get_avatar( ap_activity_user_id(), 35 ); ?></a>
						</div>
						<div class="no-overflow">
							<a href="<?php ap_notification_the_permalink() ?>" class="clearfix">
								<span class="ap-notification-content"><?php ap_activity_the_content(); ?></span>
								<span class="ap-notification-time"><?php ap_activity_the_date(); ?></span>
							</a>
						</div>
						
					</div>
				<?php endwhile; ?>
				<div class="ap-notification-more clearfix"><a href="<?php echo ap_user_link(get_current_user_id(), 'notification'); ?>"><?php _e('View all notifications', 'ap'); ?></a></div>
			</div>
		</div>
	<?php else: ?>
		<div class="ap-no-notification"><?php _e('No notification', 'ap'); ?></div>
	<?php endif; ?>
	
</div>
