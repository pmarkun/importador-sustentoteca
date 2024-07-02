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

function extract_youtube_id($url) {
    $pattern = '/(?<=v=)[a-zA-Z0-9-]+(?=&)|(?<=[0-9]\/)[^&\n]+|(?<=v=)[^&\n]+/i';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[0];
    }
    return false;
}


function process_media_urls($media_array) {
    $html_output = '<div class="media-container">';

    foreach ($media_array as $url) {
        $url = trim($url);
        $url = rtrim($url, '#');
        if (empty($url)) {
            continue;
        }
        
        // Validar a URL (simplesmente verificando se parece com uma URL)
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            continue; // Pula URLs que não são válidas
        }

        $url = esc_url($url); // Escape da URL para segurança
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            $video_id = extract_youtube_id($url);
            if (!empty($video_id)) {
                $html_output .= '<div class="media-item youtube-video" style="border-radius: 20px; border: 1px solid #61CE70; padding: 25px; margin:0 auto 15px auto; max-width: 600px; width: 100%;"><div class="youtube-player" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;"><iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 20px;"></iframe></div></div>';
            } else {
                $html_output .= '<!-- YouTube URL found but no video ID extracted: ' . htmlspecialchars($url) . ' -->';
            }
        } elseif (preg_match('/\.pdf$/i', $url)) {
            $html_output .= '<div class="media-item pdf-viewer" style="border-radius: 20px; border: 1px solid #61CE70; padding: 25px; margin-bottom: 15px; max-width: 600px; width: 100%;"><embed src="' . $url . '" type="application/pdf" width="100%" height="500px" style="border-radius: 20px;" /></div>';
        } else {
            $html_output .= '<div class="media-item normal-link" style="border-radius: 20px; border: 1px solid #61CE70; padding: 25px; margin-bottom: 15px; max-width: 600px; width: 100%;"><a href="' . $url . '" target="_blank" style="text-decoration: none; color: #0073aa; display: flex; align-items: center;"><i class="eicon-link" style="margin-right: 10px;"></i> ' . htmlspecialchars($url) . '</a></div>';
        }
    }
    $html_output .= '</div>';
    return $html_output;
}

function media_sustentoteca_shortcode($atts) {
    // Atributos do shortcode
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID(), // Pega o ID do post atual se não for especificado
    ), $atts);

    // Pega o campo de mídia do post especificado
    $media_field = get_post_meta($atts['post_id'], 'media'); // Substitua 'media' pelo nome real do seu campo personalizado
    
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

function get_search_results_context($search_query) {
    // Obter as opções do WPSOLR
    $wpsolr_options = wpsolr\core\classes\services\WPSOLR_Service_Container::getOption();

    // Criar uma nova instância do WPSOLR_Query
    $wpsolr_query = wpsolr\core\classes\services\WPSOLR_Service_Container::get_query();

    // Configurar os parâmetros da busca
    $wpsolr_query->set_wpsolr_query($search_query);
    $wpsolr_query->wpsolr_set_nb_results_by_page(5);

    // Adicionar configurações específicas se necessário, como ordenação por relevância
    $wpsolr_query->set('orderby', 'relevance'); // Ordenar por relevância
    $wpsolr_query->set('order', 'DESC'); // Em ordem decrescente, se aplicável

    // Executar a busca usando WPSOLR
    try {
        $wpsolr_query->get_posts();
        $posts = $wpsolr_query->posts;
    } catch (Exception $e) {
        return 'Erro ao executar a busca: ' . $e->getMessage();
    }

    // Se não houver resultados, mostrar mensagem apropriada
    if (empty($posts)) {
        return 'Nenhum resultado encontrado.';
    }

    // Preparando a string de saída
    $output = '';

    foreach ($posts as $post) {
        $title = wp_strip_all_tags($post->post_title);
        $content = wp_strip_all_tags($post->post_content);
        $output .= $title . "\n" . $content . "\n\n";
    }

    return $output;
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
                body: JSON.stringify({ prompt: searchQuery, context: get_search_results_context(searchQuery) }) // Adapte conforme necessário para passar o contexto
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