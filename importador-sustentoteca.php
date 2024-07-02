<?php
/*
Plugin Name: Importador Sustentoteca
Description: Um plugin simples para importar conteúdos de um arquivo CSV para a Sustentoteca.
Version: 1.0
Author: Seu Nome
*/

function importador_sustentoteca_menu() {
    add_menu_page(
        'Importador Sustentoteca', // Page title
        'Importador Sustentoteca', // Menu title
        'manage_options', // Capability
        'importador-sustentoteca', // Menu slug
        'importador_sustentoteca_page' // Callback function
    );
}

add_action('admin_menu', 'importador_sustentoteca_menu');

function process_media_urls($media_array) {
    $html_output = '';

    foreach ($media_array as $url) {
        $url = trim($url);
        if (empty($url)) {
            continue;
        }
        
        // Validar a URL (simplesmente verificando se parece com uma URL)
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            continue; // Pula URLs que não são válidas
        }

        $url = esc_url($url); // Escape da URL para segurança

        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            $video_id = '';
            // Atualização da expressão regular para capturar o ID do vídeo em várias formas de URLs do YouTube
            if (preg_match('/(?:youtube\.com\/(?:[^\/\n\s]*\/)*(?:v|embed|watch)\?v=|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $match)) {
                $video_id = $match[1];
            }
            if (!empty($video_id)) {
                $html_output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;"><iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div><br>';

            } else {
                $html_output .= '<!-- YouTube URL found but no video ID extracted: ' . htmlspecialchars($url) . ' -->';
            }
        } else {
            $html_output .= '<h2><a href="' . $url . '" target="_blank" style="text-decoration: none; color: #0073aa;"><i class="eco-icon-external-link"></i> ' . htmlspecialchars($url) . '</a></h2><br>';
        }
    }
    return $html_output;
}

function media_sustentoteca_shortcode($atts) {
    // Atributos do shortcode
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID(), // Pega o ID do post atual se não for especificado
    ), $atts);

    // Pega o campo de mídia do post especificado
    $media_field = get_post_meta($post_id, 'media', true); // Substitua 'media' pelo nome real do seu campo personalizado

    if (is_array($media_field)) {
        return process_media_urls($media_field); // Chama a função para processar e retornar o HTML
    } else {
        return 'Nenhuma mídia encontrada';
    }
}

add_shortcode('media_sustentoteca', 'media_sustentoteca_shortcode');

function importador_sustentoteca_page() {
    // Verifica se o usuário enviou o formulário e salva a API key
    if (isset($_POST['save_api_key'])) {
        update_option('sustentoteca_api_key', sanitize_text_field($_POST['api_key']));
        echo '<p>API key salva com sucesso!</p>';
    }

    // Recupera a API key armazenada
    $api_key = get_option('sustentoteca_api_key', '');

    ?>
    <div class="wrap">
        <h1>Configurações do Importador Sustentoteca</h1>
        <h2>Configurar API Key</h2>
        <form method="post">
            <label for="api_key">API Key:</label>
            <input type="text" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
            <br><br>
            <input type="submit" name="save_api_key" value="Salvar API Key">
        </form>

        <h2>Importar Conteúdo de CSV para Sustentoteca</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" id="import_csv" name="import_csv" accept=".csv">
            <input type="submit" name="submit_csv" value="Importar">
            <input type="button" value="Remover Todos os Posts" onclick="confirmDeletion()">
        </form>
    </div>
    <script>
    function confirmDeletion() {
        if (confirm("Tem certeza que deseja remover todos os posts do tipo conteúdo? Esta ação é irreversível!")) {
            window.location.href = "<?php echo admin_url('admin.php?page=importador-sustentoteca&delete_all=true'); ?>";
        }
    }
    </script>
    <?php
    if (isset($_FILES['import_csv'])) {
        $file = $_FILES['import_csv']['tmp_name'];
        $result = importador_conteudo($file);
        if (is_wp_error($result)) {
            echo '<p>Erro: ' . $result->get_error_message() . '</p>';
        } else {
            echo '<p>Importação completada com sucesso!</p>';
        }
    }

    if (isset($_GET['delete_all']) && $_GET['delete_all'] == 'true') {
        delete_all_conteudo_posts();
    }
}

function delete_all_conteudo_posts() {
    $pod = pods('conteudo');  // Substitua 'conteudo' pelo nome real do seu Pod
	
	// Check if the pod item exists.
	if ( $pod->exists() ) {
    	echo '<p>Pelo menos o POD existe</p>';
	}
    
	$params = array(
        'limit' => -1  // Busca todos os itens
    );
    $pod->find($params);

    $total_items = $pod->total();
    echo '<p>Total de posts encontrados: ' . $total_items . '</p>';

    while ($pod->fetch()) {
        $pod->delete($pod->id());
    }

    echo '<p>Todos os posts foram removidos com sucesso.</p>';
}


include 'import-functions.php';

function register_chatgpt_endpoint() {
    register_rest_route('sustentoteca/v1', '/process_with_chatgpt', array(
        'methods' => 'POST',
        'callback' => 'process_with_chatgpt_endpoint',
        'permission_callback' => function ($request) {
            // Verifica o nonce
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_nonce_invalid', 'Nonce inválido', array('status' => 403));
            }
            return true;
        },
    ));
}
add_action('rest_api_init', 'register_chatgpt_endpoint');

function process_with_chatgpt_endpoint(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $prompt = sanitize_text_field($params['prompt']);
    $context = sanitize_text_field($params['context']);
    return process_with_chatgpt($prompt, $context);
}

function process_with_chatgpt($prompt, $context) {
    // Recupera a API key armazenada
    $api_key = get_option('sustentoteca_api_key', '');
    if (empty($api_key)) {
        return 'Erro: A API key não está configurada.';
    }

    $api_url = 'https://api.openai.com/v1/chat/completions';

    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    );

    $data = array(
        'model' => 'gpt-3.5-turbo-0125',
        'messages' => array(
            array('role' => 'system', 'content' => 'Você é um assistente que ajuda os usuários a navegar pelo conteúdo da Sustentoteca, um repositório de informações sobre sustentabilidade. Aqui estão os resultados de uma busca pelo termo '.$prompt.'. Com base nos resultados encontrados, elabore um parágrafo bem curto que auxilie o usuário a entender e navegar pelo tema pesquisado, destacando em negrito com <b></b> os trechos que se conectam com documentos na plataforma:'),
            array('role' => 'user', 'content' => 'Termo buscado:' . $prompt . "\n\nResultados:\n" . $context),
        ),
        'max_tokens' => 200,
    );

    $options = array(
        'headers' => $headers,
        'body' => json_encode($data),
        'timeout' => 45,
    );

    $response = wp_remote_post($api_url, $options);
    if (is_wp_error($response)) {
        return 'Erro ao conectar com a API de IA: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        return 'Erro ao processar a resposta da IA.' . $result;
    }
}

function wpsolr_search_results_shortcode() {
    // Gera um nonce
    $nonce = wp_create_nonce('wp_rest');
    
    ob_start();
    ?>
    <div id="search-results-container">
        <div id="loading-indicator" style="display: none;">
            <p>Processando com IA...</p>
            <div class="spinner"></div>
        </div>
        <div id="search-results"></div>
    </div>
    <style>
        .spinner {
            margin: 16px auto;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0, 0, 0, .1);
            border-radius: 50%;
            border-top-color: #0073aa;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var urlParams = new URLSearchParams(window.location.search);
        var searchQuery = urlParams.get('s');
        
        if (searchQuery) {
            document.getElementById('loading-indicator').style.display = 'block';
            fetch('<?php echo esc_url(rest_url('sustentoteca/v1/process_with_chatgpt')); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_attr($nonce); ?>'
                },
                body: JSON.stringify({ prompt: searchQuery, context: '' }) // Adapte conforme necessário para passar o contexto
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading-indicator').style.display = 'none';
                if (data) {
                    document.getElementById('search-results').innerHTML = data;
                } else {
                    document.getElementById('search-results').innerHTML = 'Erro ao processar a resposta da IA.';
                }
            })
            .catch(error => {
                document.getElementById('loading-indicator').style.display = 'none';
                console.error('Erro:', error);
                document.getElementById('search-results').innerHTML = 'Erro ao conectar com a API de IA.';
            });
        } else {
            document.getElementById('search-results').innerHTML = 'Por favor, forneça um termo de busca na URL como ?s=termo.';
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('search_airesults', 'wpsolr_search_results_shortcode');