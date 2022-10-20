<?php
	defined( 'ABSPATH' ) || exit;
?>

<div id="mfn-dashboard" class="mfn-ui mfn-dashboard" data-page="tools">

	<input type="hidden" name="mfn-builder-nonce" value="<?php echo wp_create_nonce( 'mfn-builder-nonce' ); ?>">

	<?php
		// header
		include_once get_theme_file_path('/functions/admin/templates/parts/header.php');
	?>

	<div class="mfn-wrapper">

		<?php
			// subheader
			$current = 'tools';
			include_once get_theme_file_path('/functions/admin/templates/parts/subheader.php');
		?>

		<div class="mfn-dashboard-wrapper">
			<div class="mfn-row">

				<div class="row-column row-column-4">

					<div class="mfn-card mfn-shadow-1" data-card="tool-item">
						<div class="card-content">
							<div class="tool-logo">
								<span class="local-css">Local <b>CSS</b>
								</span>
							</div>
							<p>Some BeBuilder styles are saved in CSS files in the uploads folder and database. Recreate those files and settings.</p>
							<a data-nonce="<?php echo wp_create_nonce( 'mfn-builder-nonce' ); ?>" data-action="mfn_regenerate_css" class="mfn-btn mfn-btn-fw tools-do-ajax" href="#">
								<span class="btn-wrapper"><?php esc_html_e( 'Regenerate files', 'mfn-opts' ); ?></span>
							</a>
						</div>
					</div>

				</div>

				<div class="row-column row-column-4">

					<div class="mfn-card mfn-shadow-1" data-card="tool-item">
						<div class="card-content">
							<div class="tool-logo">
								<span class="local-fonts">Local <b>Fonts</b>
								</span>
							</div>
							<p>You chose to Cache fonts local in <a target="_blank" href="admin.php?page=be-options#performance-general">Performance</a> tab. </p>
							<p>Please Regenerate fonts every time you change anything in <a target="_blank" href="admin.php?page=be-options#font-family">Fonts &gt; Family</a> tab. </p>
							<a data-nonce="<?php echo wp_create_nonce( 'mfn-builder-nonce' ); ?>" data-action="mfn_regenerate_fonts" href="#" class="mfn-btn mfn-btn-fw tools-do-ajax">
								<span class="btn-wrapper"><?php esc_html_e( 'Regenerate fonts', 'mfn-opts' ); ?></span>
							</a>
						</div>
					</div>

				</div>

				<div class="row-column row-column-4">

					<div class="mfn-card mfn-shadow-1" data-card="tool-item">
						<div class="card-content">
							<div class="tool-logo">
								<span class="delete-history">Delete <b>History</b>
								</span>
							</div>
							<p>Delete all BeBuilder history entries.</p>
							<a data-nonce="<?php echo wp_create_nonce( 'mfn-builder-nonce' ); ?>" data-action="mfn_history_delete" href="#" class="mfn-btn mfn-btn-fw tools-do-ajax confirm">
								<span class="btn-wrapper"><?php esc_html_e( 'Delete', 'mfn-opts' ); ?></span>
							</a>
						</div>
					</div>

				</div>

			</div>
		</div>

		<?php
			// footer
			include_once get_theme_file_path('/functions/admin/templates/parts/footer.php');
		?>

	</div>

</div>
