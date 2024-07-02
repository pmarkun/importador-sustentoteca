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

    // Aumentar o tempo máximo de execução e o limite de memória
    set_time_limit(0);
    ini_set('memory_limit', '256M');

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
        
        // Barra de progresso inicial
        echo '<p>Importando itens: <span id="progress">0%</span></p>';
        echo '<div style="width: 100%; background-color: #f3f3f3;"><div id="progress-bar" style="width: 0%; height: 20px; background-color: #4caf50;"></div></div>';
        echo '<script>
        function updateProgress(percent) {
            document.getElementById("progress").innerText = percent + "%";
            document.getElementById("progress-bar").style.width = percent + "%";
        }
        </script>';

        $batch_size = 50; // Tamanho do lote
        $current = 0;
        $processed = 0;

        foreach ( $parsed_data['items'] as $row ) {
            $params = array(
                'where' => sprintf("card.meta_value = '%s'", addslashes($row['card']))
            );
            $pod->find($params);
            if ($pod->total() > 0) {
                // If post exists, fetch its ID and update
                $pod->fetch();
                $item_id = $pod->id();
                //error_log('Updating existing item ID: ' . $item_id);
            } else {
                // If post does not exist, create a new one
                $item_id = 0;
                //error_log('Creating new item for card: ' . $row['card']);
            }

            // Collect all URL fields
            $media_urls = [];
            foreach ($row as $key => $value) {
                if (strpos($key, 'url_') === 0 && !empty($value)) {
                    $clean_url = trim($value);
                    $clean_url = rtrim($clean_url, '#');
                    $media_urls[] = $clean_url;
                }
            }

            $data = array(
                'post_title' => $row['title'],
                'post_content' => $row['description'],
                'card' => $row['card'],
                'media' => $media_urls
            );

            if ($item_id > 0) {
                $pod->save($data, null, $item_id);
            } else {
                $pod->add($data);
            }

            $processed++;
            // Atualiza a barra de progresso
            $percent = intval(($processed / $total_found) * 100);
            echo '<script>updateProgress(' . $percent . ');</script>';
            flush(); // Força a saída do buffer para o navegador

            $current++;

            // Processar em lotes menores para evitar timeout
            if ($current >= $batch_size) {
                $current = 0;
                // Pausa para evitar timeout
                sleep(1);
            }
        }
    } else {
        error_log('No items found to import.');
        return new WP_Error( 'no_items', 'No items to import.' );
    }
    return true;
}
