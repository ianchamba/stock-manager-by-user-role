<?php
// Adiciona um botão "Regras de Estoque por Função de Usuário" e as opções abaixo para todas as variações de produtos
function adicionar_botao_opcoes_variacoes( $loop, $variation_data, $variation ) {
    echo '<div class="options_group">';

    $user_roles = wp_roles()->get_names();
    $default_roles = implode( ',', array_keys( $user_roles ) );

    // Botão "Regras de Estoque por Função de Usuário"
    echo '<button type="button" class="button toggle-regras-estoque" data-target="regras-estoque-' . $loop . '">' . __('Regras de Estoque por Função de Usuário', 'woocommerce') . '</button>';

    // Container para as opções
    echo '<div class="regras-estoque-' . $loop . '" style="display: none;">';

    // Campo "Status por Usuário"
    woocommerce_wp_text_input(
        array(
            'id'          => '_status_por_usuario_' . $loop,
            'label'       => 'Status por Usuário',
            'placeholder' => __( 'Digite os roles separados por vírgula', 'woocommerce' ),
            'desc_tip'    => 'true',
            'description' => __( 'Digite os roles de usuário separados por vírgula. Deixe em branco para todos os papéis.', 'woocommerce' ),
            'value'       => get_post_meta( $variation->ID, '_status_por_usuario', true ) ?: $default_roles,
        )
    );

    // Campo "Estoque"
    woocommerce_wp_text_input(
        array(
            'id'          => '_estoque_' . $loop,
            'label'       => 'Estoque',
            'placeholder' => __( 'Digite a quantidade em estoque', 'woocommerce' ),
            'desc_tip'    => 'true',
            'description' => __( 'Digite a quantidade em estoque para esta variação.', 'woocommerce' ),
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '0',
            ),
            'value'       => get_post_meta( $variation->ID, '_estoque', true ),
        )
    );

    // Campo "Descrição do Estoque"
    woocommerce_wp_text_input(
        array(
            'id'          => '_descricao_estoque_' . $loop,
            'label'       => 'Descrição do Estoque',
            'placeholder' => __( 'Digite uma descrição do estoque', 'woocommerce' ),
            'desc_tip'    => 'true',
            'description' => __( 'Digite uma descrição do estoque para esta variação.', 'woocommerce' ),
            'value'       => get_post_meta( $variation->ID, '_descricao_estoque', true ),
        )
    );

    echo '</div>'; // Fecha o container

    echo '</div>'; // Fecha options_group
}

add_action( 'woocommerce_variation_options_pricing', 'adicionar_botao_opcoes_variacoes', 10, 3 );

// Adiciona scripts para mostrar/ocultar as opções quando o botão é clicado
function adicionar_scripts_mostrar_ocultar() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.toggle-regras-estoque', function() {
                var target = $(this).data('target');
                $('.' + target).slideToggle();
            });
        });
    </script>
    <?php
}

add_action( 'admin_footer', 'adicionar_scripts_mostrar_ocultar' );

// Salva os valores para "Status por Usuário", "Estoque" e "Descrição do Estoque" ao salvar a variação do produto
function salvar_opcoes_variacoes( $variation_id, $i ) {
    // Salva "Status por Usuário"
    $status_por_usuario = isset( $_POST['_status_por_usuario_' . $i] ) ? sanitize_text_field( $_POST['_status_por_usuario_' . $i] ) : '';
    update_post_meta( $variation_id, '_status_por_usuario', $status_por_usuario );

    // Salva "Estoque"
    $estoque = isset( $_POST['_estoque_' . $i] ) ? intval( $_POST['_estoque_' . $i] ) : 0;
    update_post_meta( $variation_id, '_estoque', $estoque );

    // Salva "Descrição do Estoque"
    $descricao_estoque = isset( $_POST['_descricao_estoque_' . $i] ) ? sanitize_text_field( $_POST['_descricao_estoque_' . $i] ) : '';
    update_post_meta( $variation_id, '_descricao_estoque', $descricao_estoque );
}

add_action( 'woocommerce_save_product_variation', 'salvar_opcoes_variacoes', 10, 2 );

// Adiciona verificação antes de adicionar ao carrinho
function verificar_estoque_e_usuario( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
    if ( $variation_id ) {
        $estoque = get_post_meta( $variation_id, '_estoque', true );

        // Se o estoque for 0, verifica a função do usuário
        if ( $estoque === '0' ) {
            $status_por_usuario = get_post_meta( $variation_id, '_status_por_usuario', true );
            $descricao_estoque = get_post_meta( $variation_id, '_descricao_estoque', true );

            // Verifica se o usuário atual tem pelo menos uma das funções específicas
            $current_user = wp_get_current_user();
            $user_roles = $current_user->roles;

            $funcoes_permitidas = explode( ',', $status_por_usuario );

            // Se o usuário tiver pelo menos uma das funções específicas, bloqueia a compra
            if ( !empty( array_intersect( $funcoes_permitidas, $user_roles ) ) ) {
                $mensagem_erro = !empty( $descricao_estoque ) ? $descricao_estoque : __( 'Desculpe, você não tem permissão para comprar esta variação.', 'woocommerce' );
                wc_add_notice( $mensagem_erro, 'error' );
                return false;
            }
        }
    }

    return $passed;
}

add_filter( 'woocommerce_add_to_cart_validation', 'verificar_estoque_e_usuario', 10, 6 );

// Adiciona verificação antes de ir para o checkout
function verificar_estoque_e_usuario_checkout() {
    // Obtém os itens do carrinho
    $carrinho = WC()->cart->get_cart();

    foreach ( $carrinho as $item ) {
        $product_id = $item['product_id'];
        $variation_id = $item['variation_id'];

        $estoque = get_post_meta( $variation_id, '_estoque', true );

        // Se o estoque for 0, verifica a função do usuário
        if ( $estoque === '0' ) {
            $status_por_usuario = get_post_meta( $variation_id, '_status_por_usuario', true );
            $descricao_estoque = get_post_meta( $variation_id, '_descricao_estoque', true );

            // Verifica se o usuário atual tem a função específica
            $current_user = wp_get_current_user();
            $user_roles = $current_user->roles;
			
			$funcoes_permitidas = explode( ',', $status_por_usuario );

            // Se o usuário tiver pelo menos uma das funções específicas, bloqueia a compra
            if ( !empty( array_intersect( $funcoes_permitidas, $user_roles ) ) ) {
                $mensagem_erro = !empty( $descricao_estoque ) ? $descricao_estoque : __( 'Desculpe, você não tem permissão para comprar esta variação.', 'woocommerce' );
                wc_add_notice( $mensagem_erro, 'error' );
                return false;
            }
        }
    }

    return true;
}

add_action( 'woocommerce_check_cart_items', 'verificar_estoque_e_usuario_checkout' );

// Adiciona mensagem no frontend quando o estoque é zero e a função do usuário está presente
function mensagem_estoque_usuario_frontend() {
    global $product;

    // Verifica se é um produto variável
    if ( $product->is_type('variable') ) {
        $variations = $product->get_available_variations();

        foreach ( $variations as $variation ) {
            $estoque = get_post_meta( $variation['variation_id'], '_estoque', true );
            $status_por_usuario = get_post_meta( $variation['variation_id'], '_status_por_usuario', true );
            $descricao_estoque = get_post_meta( $variation['variation_id'], '_descricao_estoque', true );

            // Verifica se o estoque é 0 e o usuário tem pelo menos uma das funções específicas
            $current_user = wp_get_current_user();
            $user_roles = $current_user->roles;
            $funcoes_permitidas = explode( ',', $status_por_usuario );

            if ( $estoque === '0' && !empty( array_intersect( $funcoes_permitidas, $user_roles ) ) ) {
                // Exibe a mensagem acima do botão de compra
                echo '<p class="estoque-usuario-mensagem">' . esc_html( $descricao_estoque ) . '</p>';
                add_action( 'woocommerce_before_single_product', 'wc_output_product_price', 10 );
                remove_action( 'woocommerce_single_product_summary', 'wc_output_product_price', 10 );
                add_action( 'woocommerce_before_single_product', 'woocommerce_template_single_price', 11 );
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 11 );
            }
        }
    }
}

add_action( 'woocommerce_single_product_summary', 'mensagem_estoque_usuario_frontend', 9 );
