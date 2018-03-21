<?

class EtxtAntiPlagiat
{
	// путь до сервера
	private $serverUrl = array (
		'server' => 'http://136.243.95.154:8685/etxt_antiplagiat',
	);

	// тип сервера по умолчанию
	public $serverType = 'server';
	// путь до веб части проверки
	private $localServer = '';
	// путь для получения результата
	public $localUrl    = '';
	// массив объектов на проверку
	private $ItemsToCheck = array();
	// папка для xml заданий
	private $xmlPath = 'wp-content/uploads/xml/';
	// типы объектов для проверки
	private $typesObjects = array ('text');
	// ключ использования шифра
	public $useCrypt = 1;
	// флаг соединения с сервером
	public $isConnect = false;
	// статус ошибки
	public $Error = '';
	// флаг-значение приоритета пакета
	public $sort = 0;
	public $logger;

	// конструктор, параметр - имя xml файла
	public function __construct($path = '', $my_crypt = 1, $serverType = 'server', $xmlPath = null, $localServer = null, LogWrite $logger)
	{

		if ($path) {
			if ($localServer) $this->localServer = $localServer . $path;
			if ($xmlPath) $this->xmlPath = $xmlPath . $path;
		}

		$this->logger = $logger;
		$this->localUrl = "http://" . $_SERVER['HTTP_HOST'] . "/wp-admin/admin-post.php?action=uniq_upload";

		$this->useCrypt = $my_crypt;

		if ($this->useCrypt == 1) $this->useCrypt = CHECK_KEY;

		$this->serverType = $serverType;

		// пингуем сервер
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl[$this->serverType]);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "try=1");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$json = curl_exec($ch);
		curl_close($ch);

		// устанавливаем результат пинга
		$this->isConnect = $json ? $json : false;
		$this->isConnect = str_replace('\,', ',', $this->isConnect);
		if (($tmp = json_decode($this->isConnect)) && $tmp->Code == 7) {
			$this->isConnect = false;
			$this->Error = $tmp->Description;
		}
	}

	// функция добавления объекта на проверку
	public function addItemToCheck($data)
	{
		if (!$data['id'] || !in_array($data['type'], $this->typesObjects)) return false;

		$this->ItemsToCheck[] = array ('id' => $data['id'], 'text' => (isset($data['text']) ? $this->codeText($data['text']) : ''), 'type' => $data['type'], 'name' => $this->codeText($data['title']), 'uservars' => isset($data['uservars']) ? $data['uservars'] : array());

		return true;
	}

	// функция кодирования текста
	private function codeText($text)
	{
		return base64_encode($text);
	}

	// функция выполнения запроса на проверку
	public function execRequest($create = 1)
	{

		// пытаемся создать xml файл с заданиями
		if ($create && !$this->createXml()) return false;

		$postFields = "xmlUrl=".$this->localServer ."&xmlAnswerUrl=".$this->localUrl.($this->sort ? '&sort='.$this->sort : '');

		// отправляем запрос на сервер
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl[$this->serverType]);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$json = curl_exec($ch);
		curl_close($ch);

		return str_replace('\,', ',', $json);
	}

	// функция построения xml файла заданий
	private function createXml()
	{
		$string = '<?xml version="1.0" encoding="UTF-8" ?'.'><root>';

		$string .= '<serverType>'.$this->serverType.'</serverType>';

		foreach ($this->ItemsToCheck as $item) {
			$string .= '
                <entry>
                    <id>'.$item['id'].'</id>
                    <type>'.$item['type'].'</type>';

			if (isset($item['uservars']) && $item['uservars'] && is_array($item['uservars'])) {
				$string .= '
                    <uservars>';
				foreach ($item['uservars'] as $key => $uservar)
					$string .= '
                        <'.$key.'>'.$uservar.'</'.$key.'>';

				$string .= '
                    </uservars>';
			}
			$string .='
                    <name>'.$item['name'].'</name>'.
			          (isset($item['text']) && $item['text'] ? '<text>'.$item['text'].'</text>' : '').'
                </entry>';
		}

		$string .= '</root>';

		if (is_file($this->xmlPath)) unlink($this->xmlPath);

		// шифруем данные
		if ($this->useCrypt) {
			$td = mcrypt_module_open (MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
			mcrypt_generic_init ($td, $this->useCrypt, $this->useCrypt);
			$string = mcrypt_generic ($td, $string);
			mcrypt_generic_deinit ($td);
			mcrypt_module_close ($td);
		}

		// сохраняем файл
		$f = @fopen($this->xmlPath, 'w');
		if (!$f) {
			$this->logger->logWriter("Failed open file # ".$this->xmlPath." # on write");
			die;
		}

		fwrite($f, $string, strlen($string));
		fclose($f);

		if (is_file($this->xmlPath)) return true;

		return false;
	}
}

?>