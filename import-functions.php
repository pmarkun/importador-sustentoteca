<?php
/**
 * Import CSV for Pods with Debug, updating existing items if they exist
 *
 * @param string $file File location
 *
 * @return true|WP_Error Returns true on success, WP_Error if there was a problem
 */
function importador_conteudo( $file ) {
    if ( ! is_readable( $file ) ) {
        return new WP_Error( 'file_read_error', sprintf( 'Can\'t read file: %s', $file ) );
    }
    if ( ! function_exists( 'pods_migrate' ) ) {
        return new WP_Error( 'missing_function', 'pods_migrate function not found' );
    }

    $migrate = pods_migrate();
    $contents = file_get_contents( $file );
    $parsed_data = $migrate->parse_sv( $contents, ',' );
    $pod = pods( 'conteudo' ); // Update to your pod name
    if ( $pod->exists() ) {
        echo '<p>Pelo menos o POD existe</p>';
    }
    
    if ( ! empty( $parsed_data['items'] ) ) {
        $total_found = count( $parsed_data['items'] );
        error_log('Starting import of ' . $total_found . ' items.');
        foreach ( $parsed_data['items'] as $row ) {
            $params = array(
                'where' => sprintf("card.meta_value = '%s'", addslashes($row['carta']))
            );
            $pod->find($params);
            if ($pod->total() > 0) {
                // If post exists, fetch its ID and update
                $pod->fetch();
                $item_id = $pod->id();
                error_log('Updating existing item ID: ' . $item_id);
            } else {
                // If post does not exist, create a new one
                $item_id = 0;
                error_log('Creating new item for card: ' . $row['carta']);
            }

            // Collect all URL fields
            $media_urls = [];
            foreach ($row as $key => $value) {
                if (strpos($key, 'url_') === 0 && !empty($value)) {
                    $media_urls[] = $value;
                }
            }

            $data = array(
                'post_title' => $row['conteúdo_linha_do_tempo'],
                'post_content' => $row['descrição_-_porque_é_importante'],
                'card' => $row['carta'],
                'media' => $media_urls
            );

            if ($item_id > 0) {
                $pod->save($data, null, $item_id);
            } else {
                $pod->add($data);
            }
        }
    } else {
        error_log('No items found to import.');
        return new WP_Error( 'no_items', 'No items to import.' );
    }
    return true;
}

