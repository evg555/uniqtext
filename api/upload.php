<?php

$logger = new LogWrite(plugin_dir_path( __FILE__ ). "log.txt");

$logger->logWriter( 'Получен ответ от сервера ' . $_SERVER['REMOTE_ADDR']);

// проверка данных в ответе
if (!isset($_POST['Xml']) || !$_POST['Xml']) {
	$logger->logWriter( 'Данные в ответе сервера не найдены');
	exit();
}

$_POST['Xml'] = str_replace(' ', '+', $_POST['Xml']);

// расшифровываем данные
$td = mcrypt_module_open (MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
mcrypt_generic_init ($td, CHECK_KEY, CHECK_KEY);
$_POST['Xml'] = mdecrypt_generic ($td, base64_decode($_POST['Xml']));

// устанавливаем перехват ошибок
libxml_use_internal_errors(true);

$f = @fopen(plugin_dir_path( __FILE__ ). "xml.bin", 'w');
if (!$f) {
	$this->logger->logWriter("Failed open file # ".plugin_dir_path( __FILE__ ) . "xml.bin # on write");
	die;
}

fwrite($f, $_POST['Xml']);
fclose($f);

//$file = file_get_contents(plugin_dir_path( __FILE__ ). "xml.bin");

// пытаемся преобразовать xml в объект
if ($xml = simplexml_load_string($_POST['Xml'])) {
	foreach ($xml->entry as $item) {
		$xml_id = (int)$item->id;
		$items = $item->ftext;
	}
}


$logger->logWriter( 'id: ' . $xml_id);

if ($xml_id) {
	//Если текст уникален не на 100%
	if ( $items ) {
		$uniq = (int)$items['uniq'];
		$text = base64_decode( $items[0]);
	} else {
		$uniq = 100;
		$text = '';
	}

	$logger->logWriter( 'uniq: ' . $uniq);
	$logger->logWriter( 'text: ' . $text);


	update_post_meta($xml_id, 'uniq', $uniq );
	update_post_meta($xml_id, 'ftext', wp_slash($text));
	update_post_meta($xml_id, 'uniq_time', time());
}

echo "ok";



