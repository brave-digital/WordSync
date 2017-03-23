<?php

/**
 * Provide a settings view for the plugin
 **
 * @link       http://bravedigital.com
 * @since      0.1.0
 *
 * @package    BraveWpSync
 * @subpackage BraveWpSync/admin/partials
 */

	/** @var BraveWordsync_Admin $wordsyncadmin */
	$wordsyncadmin = $GLOBALS['wordsync_admin']; //Set in the class-wordsync-admin.php file just before this file is included.

	// check if the user have submitted the settings
	// wordpress will add the "settings-updated" $_GET parameter to the url
	if (isset($_GET['settings-updated']))
	{
		// add settings saved message with the class of "updated"
		add_settings_error('wordsync_messages', 'wordsync_messages', __('Settings Saved', 'wordsync'), 'updated');
	}

?>
<div class="wrap">

	<div class="bravewrap">
		<div class="braveheader fullheader">
			<div class="logo"></div>
			<span class="maintitle"><?php echo get_admin_page_title(); ?> <small><?php echo $wordsyncadmin->getWordsync()->getVersion(); ?></small></span>
			<div class="controls">
				<a class="submit-changes button button-primary" href="<?php echo $wordsyncadmin->getAdminUrl(); ?>"><?php _e('Back', 'wordsync'); ?></a>
			</div>
		</div>
		<div class="bravebody">
			<?php
				// show error/update messages
				settings_errors('wordsync_messages');
			?>

			<?php if ($wordsyncadmin->getWordsync()->isFirstRun()): ?>
				<br/>
			<div class="stuffbox welcomebox">
				<div class="inside">
					<h3>Welcome to WordSync</h3>
					<p>Hey there, thanks for trying out WordSync. Before you can sync two sites you need follow the below steps:</p>
					<h4>Setup:</h4>
					<ol>
						<li><strong>Both</strong> this site and the remote site need to have WordSync installed.</li>
						<li>Specify where your remote Wordpress install is. This must be the url of your remote site's homepage so for example: <pre style="display:inline; padding:4px">http://www.myblog.com</pre></li>
						<li>On the remote install, specify this site's url: <pre style="display:inline"><?php echo get_bloginfo('url'); ?></pre></li>
						<li>Choose a secret key that will be shared between your two sites. Enter the <i>same key</i> on <strong>both</strong> sites to allow them to talk to each other.</li>
						<li>Allow one or both sites to have their data read (check Push Enabled).</li>
						<li>Allow one or both sites to perform a sync (check Sync Enabled).</li>
					</ol>
					<p><strong>NB:</strong> You need to at least check <i>Push Enabled</i> on the remote site and <i>Sync Enabled</i> on the local site before you will be able to perform a sync.</p>
				</div>
			</div>
				<div class="stuffbox warningbox">
					<div class="inside">
						<p><strong>WARNING:</strong> This plugin modifies your site content and is still in ALPHA. It may not always perform adequately and it would be **strongly advisable** to backup your site before using WordSync. <br/>WordSync does not offer a rollback option once your data has been synced. Brave Digital does not accept any responsibility for lost or corrupted data. USE THIS PLUGIN AT YOUR OWN RISK.</p>
					</div>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
					$option_group = $wordsyncadmin->getSlug();

					echo "<input type='hidden' name='option_page' value='" . esc_attr($option_group) . "' />";
					echo '<input type="hidden" name="action" value="update" />';
					wp_nonce_field("$option_group-options", "_wpnonce", false, true);

					//Get the referrer field but remove the settings=1 parameter off the url so that when the settings are saved, the user is returned to the main WordSync page.
					$ref = wp_referer_field(false);
					$ref = str_replace("&amp;settings=1", "", $ref);
					echo $ref;

					do_settings_sections($wordsyncadmin->getSettingsPage());
					// output save settings button
					submit_button(__('Save Settings', 'wordsync'));
				?>
			</form>

		</div>

	</div>
</div>