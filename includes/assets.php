<?php declare( strict_types=1 );

\defined( 'ABSPATH' ) || exit;

/**
 * Returns an array with meta information for a given asset path. It starts from a fallback of the
 * file's last-modified time as the version with no dependencies, overlays the version and
 * dependencies from an `.asset.php` file beside the asset when one exists, and appends any extra
 * dependencies passed in. A malformed generated payload is ignored entry by entry, so a stale or
 * hand-edited `.asset.php` degrades to the fallback rather than enqueuing a broken handle.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string                  $asset_path         The path to the asset file.
 * @param   non-empty-string[]|null $extra_dependencies Any extra dependencies to include in the returned meta.
 *
 * @return  array{ version: string, dependencies: array<non-empty-string> }|null
 */
function a8csp_template_get_asset_meta( string $asset_path, ?array $extra_dependencies = null ): ?array {
	$asset_path = \str_starts_with( $asset_path, \constant( 'A8CSP_TEMPLATE_DIR_PATH' ) ) ? $asset_path : \constant( 'A8CSP_TEMPLATE_DIR_PATH' ) . $asset_path;
	if ( ! \file_exists( $asset_path ) ) {
		return null;
	}

	$asset_meta = array(
		'dependencies' => array(),
		'version'      => (string) \filemtime( $asset_path ),
	);
	if ( '' === $asset_meta['version'] ) {
		$asset_meta['version'] = a8csp_template_get_plugin_version();
	}

	$asset_pathinfo              = \pathinfo( $asset_path );
	$asset_pathinfo['dirname'] ??= '';

	$asset_meta_file = "{$asset_pathinfo['dirname']}/{$asset_pathinfo['filename']}.asset.php";
	if ( \file_exists( $asset_meta_file ) ) {
		$asset_meta_generated = require $asset_meta_file;
		if ( \is_array( $asset_meta_generated ) ) {
			if ( \is_string( $asset_meta_generated['version'] ?? null ) ) {
				$asset_meta['version'] = $asset_meta_generated['version'];
			}

			if ( \is_array( $asset_meta_generated['dependencies'] ?? null ) ) {
				$asset_meta['dependencies'] = \array_values(
					\array_filter( $asset_meta_generated['dependencies'], static fn ( mixed $dependency ): bool => \is_string( $dependency ) && '' !== $dependency )
				);
			}
		}
	}

	if ( \is_array( $extra_dependencies ) ) {
		$asset_meta['dependencies'] = \array_merge( $asset_meta['dependencies'], $extra_dependencies );
		$asset_meta['dependencies'] = \array_unique( $asset_meta['dependencies'] );
	}

	return $asset_meta;
}
