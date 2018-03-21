<?php
/**
 * Plugin Name: Uniqtext
 * Author: Evgeniy Krylov
 * Description: Проверка текста постов на уникальность
 * Version: 1.0
 */

//Получаем ключ API
define('CHECK_KEY', get_option('option_name1')['input']);

//Создаем папку xml при активации плагина
register_activation_hook( __FILE__, 'uniq_plugin_activate' );
function uniq_plugin_activate() {
    $xmlPath = wp_upload_dir()['basedir'] . '/xml';

	if (!is_dir($xmlPath)){
        mkdir($xmlPath);
    }
}

//Удаляем папку xml при активации плагина
register_deactivation_hook( __FILE__, 'uniq_plugin_deactivate' );
function uniq_plugin_deactivate(){
	$xmlPath = wp_upload_dir()['basedir'] . '/xml';

	if (is_dir($xmlPath)){
		removeDirectory($xmlPath);
	}
}

//Рекурсивное удаление папок
function removeDirectory($dir) {
	if ($objs = glob($dir."/*")) {
		foreach($objs as $obj) {
			is_dir($obj) ? removeDirectory($obj) : unlink($obj);
		}
	}
	rmdir($dir);
}


//Отправляем пост на проверку на уникальность
add_action('save_post','uniq_check_pending_posts');
function uniq_check_pending_posts($post_id){
	$post = get_post($post_id);

	if ( !current_user_can('administrator') && $post->post_status == 'pending'){
		$text = $post->post_content;
		$title = $post->post_title;
		$xmlPath = wp_upload_dir()['basedir'] . '/xml/';
		$localServer = wp_upload_dir()['baseurl'] . '/xml/';

		//Подключаем API
		require_once __DIR__ . '/api/autoload.php';

		$logger = new LogWrite(plugin_dir_path( __FILE__ ). "log.txt");

		//Cоздаем объект для формирования запроса на проверку
		$etxtPlagiat = new EtxtAntiPlagiat($post_id.'.xml', 1, 'server', $xmlPath, $localServer, $logger);

		//Доступность сервера
		if (!$etxtPlagiat->isConnect || !($tmp = json_decode($etxtPlagiat->isConnect))) {
			$logger->logWriter("Ошибка соединения с сервером проверки на уникальность:" . $etxtPlagiat->Error );

			return false;
		}

		//Выбираем объекты на проверку
		$item = array(
			'id' => $post_id,
			'text' => $text,
			'type' => 'text',
			'title' => $title
		);

		//Добавляем объект на проверку
		$etxtPlagiat->addItemToCheck($item);

		//Посылаем запрос серверу на проверку
		$etxtPlagiat->execRequest();
	}
}

//Обработчик ответа от сервера
add_action( 'admin_post_nopriv_uniq_upload', 'uniq_post_callback' );
function uniq_post_callback() {
	require_once __DIR__ .'/api/autoload.php';
	require_once __DIR__ .'/api/upload.php';
}

//Добавляем страницу настроек плагина
add_action('admin_menu','uniq_add_options_page');
function uniq_add_options_page(){
	add_options_page('Настройка плагина Uniqtext', 'Настройка Uniqtext', 'edit_theme_options', 'uniq_options', 'zuniq_render_options_page');
}

//Рендеринг страницы настроек плагина
function zuniq_render_options_page(){
	?>
    <div class="wrap">
        <h2><?php echo get_admin_page_title() ?></h2>

        <form action="options.php" method="POST">
			<?php
			settings_fields( 'option_group' );
			do_settings_sections( 'uniq_opts' );
			submit_button();
			?>
        </form>
    </div>
	<?php
}

//Регистрация секций страницы настроек плагина
add_action('admin_init', 'uniq_option_settings');
function uniq_option_settings(){		
	register_setting( 'option_group', 'option_name1', 'uniq_callback' );
	register_setting( 'option_group', 'option_name2', 'uniq_callback' );

	add_settings_section( 'uniq_section_id', 'Настройка опций плагина', '', 'uniq_opts' );

	add_settings_field('uniq_opt1', 'Ключ API', 'uniq_render_field', 'uniq_opts', 'uniq_section_id',['id' => 1] );
	add_settings_field('uniq_opt2', 'Требуемый процент уникальности, % (не ниже)', 'uniq_render_field', 'uniq_opts', 'uniq_section_id',['id' => 2] );
}

//Рендеринг полей секции на странице настроек плагина
function uniq_render_field($args){
	$option_name = "option_name" . $args['id'];
	$val = get_option($option_name);
	$val = $val['input'] ? $val['input'] : null;
	?>
    <input type="text" name="option_name<?php echo $args['id']?>[input]" value="<?php echo esc_attr( $val ) ?>" />
	<?php
}

//Фильтр данных
function uniq_callback( $options ){
	foreach( $options as $name => & $val ){
		if( $name == 'input' ){
			$val = strip_tags( $val );
		}
	}

	return $options;
}

//Добавление метабокса на странице редактирования записи для админа
add_action('add_meta_boxes', 'uniq_add_meta_box');
function uniq_add_meta_box() {
	if ( current_user_can('administrator')){
		add_meta_box( 'uniq_result', 'Результаты проверки текста на уникальность', 'uniq_meta_box_render', 'post', 'normal', 'default');
    }
}

function uniq_meta_box_render($post) {
	// получение существующих метаданных
	$uniq = get_post_meta($post->ID, 'uniq');
	$ftext = get_post_meta($post->ID, 'ftext');
	$uniqSet = get_option('option_name2')['input'];

	//Сравниваем полученный процент уникальности с тем, что задан в настройках
	if (!isset($uniq[0])){
		echo "Ожидается ответ от сервиса проверки на уникальность";
	} elseif ($uniqSet && $uniq[0] < $uniqSet){
		update_post_meta($post->ID, 'uniq_deny', 1 );

		echo '<div>Процент уникальности: <strong>' . esc_attr($uniq[0]) . '</strong></div><br>';
		echo $ftext[0];
    }
}

//Вывод сообщения о статьях, не прошедших проверку
add_action('admin_notices', 'uniq_admin_notice');
function uniq_admin_notice(){
    if (current_user_can('administrator')){
        $denyPosts = get_posts([
            'post_status' => 'pending',
            'meta_key' => 'uniq_deny',
            'meta_value' => 1
        ]);

        foreach ($denyPosts as $denyPost){
            //Выводим уведомления для записей не старше 3-х дней
	        $uniqTime = @get_post_meta($denyPost->ID, 'uniq_time')[0];

	        if (isset($uniqTime) && (time() - $uniqTime) < (3 * 24 * 60 * 60)){
		        $uniq = @get_post_meta($denyPost->ID, 'uniq')[0];

		        echo '<div class="notice notice-warning">
                        <p>Статья "' .$denyPost->post_title. '" не прошла проверку на уникальность ('.$uniq.') </p>
                    </div>';
            }
        }
    }
}
