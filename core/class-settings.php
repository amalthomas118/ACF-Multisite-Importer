<?php
// A separate class for handling all the functions happening in the front end (dashboard settings)
class MsaImporterSettings
{

	public function add_network_settings()
	{
		add_menu_page(
			__('ACF Importer', 'multisite-acf-importer'),
			__('ACF Importer', 'multisite-acf-importer'),
			'manage_network',
			'msai-importer',
			array($this, 'network_settings_page'),
			'dashicons-upload',
			60
		);
	}

	public function add_single_site_settings()
	{
		add_options_page(
			__('ACF Importer', 'multisite-acf-importer'),
			__('ACF Importer', 'multisite-acf-importer'),
			'manage_options',
			'msai-importer',
			array($this, 'single_site_settings_page')
		);
	}

	// Network settings page output
	public function network_settings_page()
	{
		?>
		<div class="wrap" id="msai-importer">
			<div class="msai-settings-container">
				<h2><span class="dashicons dashicons-upload"></span> ACF Multisite Importer</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field('msai_import_fields', 'msai_nonce'); ?>

					<div class="msai-form-group">
						<label for="select-all"><?php _e('Select Sites', 'multisite-acf-importer'); ?></label>
						<div class="msai-checkbox-group">
							<?php
							$sites = get_sites(array('deleted' => 0, 'archived' => 0, 'spam' => 0, 'public' => 1));
							foreach ($sites as $site):
								?>
								<label for="site-<?php echo $site->blog_id; ?>">
									<input type="checkbox" id="site-<?php echo $site->blog_id; ?>" name="sites[]"
										value="<?php echo $site->blog_id; ?>">
									<?php echo $site->blogname; ?>
								</label><br>
								<?php
							endforeach;
							?>
							<label for="select-all">
								<input type="checkbox" id="select-all">
								<?php _e('Select All', 'multisite-acf-importer'); ?>
							</label>
						</div>
					</div>

					<div class="msai-form-group">
						<label for="acf_json_file"><?php _e('Upload JSON File', 'multisite-acf-importer'); ?></label>
						<input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
					</div>

					<p class="submit">
						<input type="submit" name="msai_import_fields" class="button button-primary"
							value="<?php _e('Import JSON', 'multisite-acf-importer'); ?>">
					</p>

					<div id="msai-errors" class="notice notice-error is-dismissible" style="display: none;">
						<p></p>
					</div>
					<div id="msai-success" class="notice notice-success is-dismissible" style="display: none;">
						<p></p>
					</div>

				</form>
			</div>
		</div>
		<?php
	}

	// Single site settings page output
	public function single_site_settings_page()
	{
		?>
		<div class="wrap" id="msai-importer">
			<div class="msai-settings-container">
				<h2><span class="dashicons dashicons-upload"></span> ACF Importer</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field('msai_import_fields', 'msai_nonce'); ?>

					<div class="msai-form-group">
						<label for="acf_json_file"><?php _e('Upload JSON File', 'multisite-acf-importer'); ?></label>
						<input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
					</div>

					<p class="submit">
						<input type="submit" name="msai_import_fields" class="button button-primary"
							value="<?php _e('Import JSON', 'multisite-acf-importer'); ?>">
					</p>

					<div id="msai-errors" class="notice notice-error is-dismissible" style="display: none;">
						<p></p>
					</div>
					<div id="msai-success" class="notice notice-success is-dismissible" style="display: none;">
						<p></p>
					</div>

				</form>
			</div>
		</div>
		<?php
	}
}