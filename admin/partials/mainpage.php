<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://bravedigital.com
 * @since      0.1.0
 *
 * @package    BraveWpSync
 * @subpackage BraveWpSync/admin/partials
 */
	/** @var BraveWordsync_Admin $wordsyncadmin */
	$wordsyncadmin = $GLOBALS['wordsync_admin']; //Set in the class-wordsync-admin.php file just before this file is included.

	$settingsurl = add_query_arg(array('settings'=>'1'), $wordsyncadmin->getAdminUrl());
?>

<div class="wrap">

	<div class="bravewrap">
		<div class="braveheader fullheader">
			<div class="logo"></div>
			<span class="maintitle"><?php echo get_admin_page_title(); ?> <small><?php echo $wordsyncadmin->getWordsync()->getVersion(); ?></small></span>
			<div class="controls">
				<a class="button button-secondary" href="<?php echo $settingsurl; ?>"><?php _e('Settings', 'wordsync'); ?></a>
			</div>
		</div>
		<div class="bravebody">
			<br/>
			<?php
				$syncher = $wordsyncadmin->getWordsync()->getSyncher();
				$info = $syncher->getInfo();

				// show error/update messages
				settings_errors('wordsync_messages');

			?>
			<div class="stuffbox warningbox">
				<div class="inside">
					<p><strong>WARNING:</strong> This plugin modifies your site content and is still in ALPHA. It may not always perform adequately and it would be **strongly advisable** to backup your site before using WordSync. <br/>WordSync does not offer a rollback option once your data has been synced. Brave Digital does not accept any responsibility for lost or corrupted data. USE THIS PLUGIN AT YOUR OWN RISK.</p>
				</div>
			</div>

			<div class="stuffbox">
				<div class="inside">
					<div class="remoteurlbox">
						<label for="remoteurl"><?php _e('Remote Install URL: <small>(This is the url of the homepage of the remote site eg. \'www.mysite.com\')</small>', 'wordsync'); ?></label> <input name="remoteurl" id="remoteurl" type="text" class="widefat" value="<?php echo $wordsyncadmin->getWordsync()->getSetting(BraveWordsync::SETTING_REMOTE_URL); ?>" placeholder="<?php _e('Enter the URL of your remote Wordpress Install');?>"/>
					</div>
					<p><?php _e("Select which data you'd like to syncronise between the sites:", 'wordsync'); ?></p>
					<div class="processors" style="padding-top: 0;">
						<?php foreach ($info['processors'] as $proc): ?>
							<?php
								$preprocs = array();

								foreach ($proc['preprocessors'] as $pp) {
									$preprocs[] = $info['processors'][$pp]['name'];
								}

								//if (count($preprocs) == 0) $preprocs[] = '-';
							?>
							<a href="#" class="processor" data-proc="<?php echo $proc['slug']; ?>">
								<div class="selector">
									<input type="checkbox" id="processor_<?php echo $proc['slug']; ?>" title="<?php _e('Include this data in the sync', 'wordsync');?>" value="<?php echo $proc['slug']; ?>" name="processor_enabled[]"/>
								</div>

								<h3>
									<i class="dashicons-before <?php echo $proc['dashicon']; ?>"></i>
									<?php echo $proc['name']; ?>
								</h3>
								<p><?php echo $proc['desc']; ?></p>

								<?php if (count($preprocs) > 0) { ?>
								<p class="preprocessors"><?php _e('Depends on:', 'wordsync');?> <?php echo implode(',',$preprocs); ?></p>
								<?php } else { ?>
								<p class="preprocessors">&nbsp;</p>
								<?php } ?>
								<p class="status"></p>

							</a>
						<?php endforeach; ?>
					</div>
					<div class="boxfooter">
						<p class="pullleft"><?php _e('Click Sync to start the synchronisation process. You will be able to review all the data which differs between the two sites and choose what items to sync before any changes are made.', 'wordsync');?></p>
						<a href="#" class="btn-sync button button-primary"><?php _e('Sync', 'wordsync'); ?></a>
						<a href="#" class="btn-proceed button button-primary"><?php _e('Proceed', 'wordsync'); ?></a>
						<a href="#" class="hidden btn-cancelsync button button-secondary"><?php _e('Cancel Sync', 'wordsync');?></a>
					</div>
				</div>
			</div>

			<p class="error-message hidden"><span class="dashicons dashicons-no"></span> <span class="error-text"></span></p>

			<div class="statusbox stuffbox hidden">
				<h3><span class="dashicons dashicons-update"></span> <span id="progress">Contacting Remote Server</span></h3>
				<div class="progressbar">
					<div class="bar"></div>
					<div class="percent">50%</div>
				</div>
			</div>

			<div class="resultsbox stuffbox hidden">
				<div class="inside">
					<h3><?php _e('Changes:', 'wordsync');?></h3>
					<p class="results"><?php _e("Select which changes you'd like to make to <b>this Wordpress install</b>. The remote install is not affected.", 'wordsync');?></p>
					<form id="changes">
						<div class="changeslist">

						</div>
						<div class="boxfooter">
							<p class="pullleft"><?php _e("Check the changes you'd like to apply and then click the Proceed button.", 'wordsync');?></p>
							<input type="button" class="btn-proceed pullright button button-primary" value="<?php _e('Proceed', 'wordsync');?>"/>
						</div>
					</form>
				</div>
			</div>


				<div class="logbox">
					<a class="btn-showlog button button-secondary"><?php _e('Show Log'); ?></a>
					<div class="inside hidden">
						<textarea title="<?php _e('Output Log'); ?>" class="log"></textarea>
					</div>
				</div>
		</div>

	</div>
</div>
