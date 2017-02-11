<?

	/****************
	 * APIdog APIv2 *
	 ****************
	 *  14.03.2015  *
	 ****************/

	// ban for themes.*: 315482675

	/**
	 * Возвращает номер агента поддержки по VK ID
	 * @param  int $userId Идентификатор пользователя
	 * @return int         Номер агента или false
	 */
	function getAdmin ($userId = userId) {
		global $ssAdmins;
		return $ssAdmins[$userId] ?? false;
	};


	define("KB", 1024);
	define("MB", 1024 * 1024);
	define("GB", 1024 * 1024 * 1024);




	class APIdog {

		/**
		 * @deprecated
		 * @return MySQLi
		 */
		static function connect() { return getDatabase(); }

		/**
		 * @deprecated
		 * @return Mixed
		 */
		static function mysql($query, $type = 0) {
			$type = [
				1 => SQL_RESULT_ITEM,
				2 => SQL_RESULT_ITEMS,
				3 => SQL_RESULT_COUNT,
				4 => SQL_RESULT_INSERTED,
				5 => SQL_RESULT_AFFECTED,
				0 => 0
			][$type];

			return SQLquery($query, $type);
		}

		/**
		 * API VK request
		 * @param  String  $method             Метод
		 * @param  Object  $params             Параметры
		 * @param  boolean $withoutAccessToken Отправить запрос без токена
		 * @return Object                      Ответ от ВК
		 */
		static function api($method, $params, $withoutAccessToken = false) {
			if (!isset($params)) {
				$params = [];
			};

			if (!$params["v"]) {
				$params["v"] = 4.99;
			};

			if ($token = getAccessToken() && !$params["access_token"] && !$withoutAccessToken) {
				$params["access_token"] = $token;
			};

			$params["timestamp"] = time();
			$params["lang"] = "ru";

			$curl = curl_init("https://api.vk.com/method/" . $method);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
			curl_setopt($curl, CURLOPT_TIMEOUT, 10);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$json = json_decode(curl_exec($curl));
			curl_close($curl);
			return $json;
		}


		/**
		 * Загрузка изображения на ipic.su
		 * @param  String $file Путь к изображению
		 * @return Object
		 */
		static function uploadImage ($file) {
			$sizes = getImageSize($file);
			$size = fileSize($file);
			$ch = curl_init("http://ipic.su/api/index.php");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_INFILESIZE, $size);
			curl_setopt($ch, CURLOPT_POSTFIELDS, [
				"image" => new CurlFile($file, "file/exgpd", "usrapdg" . time() . ".jpg"),
				"action" => "loadimg",
				"quality" => 100
			]);
			$result = curl_exec($ch);
			unlink($file);
			curl_close($ch);
			return [
				"width" => $sizes[0],
				"height" => $sizes[1],
				"url" => $result ? "//ipic.su/img/fs/" . $result : false,
				"size" => $size
			];
		}

		/**
		 * Создание рандомной строки
		 * @deprecated
		 * @param  int $length Длина требуемой строки
		 * @return string      Строка
		 */
		static function getHash($length = 32) {
			$symbols = "0123456789abcdef";
			$l = strlen($symbols) - 1;
			$hash = "";
			for ($i = 0; $i < $length; ++$i) {
				$hash .= $symbols[rand(0, $l)];
			};
			return $hash;
		}

		/**
		 * Проверка на то, что в строке находится ссылка
		 * @param  string  $url Строка
		 * @return boolean      true, если ссылка
		 */
		static function isURL($url) {
			return (boolean) preg_match("/^((https?|ftp|market):\/\/)?([A-Za-z0-9А-Яа-яЁё-]{1,64}\.)+([A-Za-zА-Яа-я]{2,6})\/?(.*)$/iu", $url);
		}

		/**
		 * Минификация JS
		 * @return array Результат
		 */
		static function rebuildJS() {
			$url = "http://closure-compiler.appspot.com/compile";

			$files = [];
			$dir = scandir("js/");

			foreach ($dir as $file){
				if (strPos($file, "js")) {
					$files[] = file_get_contents("./js/" . $file);
				};
			};

			$fileTemp = "default.temp.js";
			$fileTarget = "default.min.js";

			file_put_contents($fileTemp, join("\n", $files));

			$js = curl($url, "output_format=json&output_info=compiled_code&output_info=warnings&output_info=errors&output_info=statistics&compilation_level=SIMPLE_OPTIMIZATIONS&warning_level=default&language=ECMASCRIPT5&output_file_name=default.min.js&code_url=http%3A%2F%2Fapidog.ru%2Flib1.3.0.js&code_url=http%3A%2F%2Fapidog.ru%2F6.5%2F" . $fileTemp . "&code_url=http%3A%2F%2Fapidog.ru%2F6.5%2Fhammer.js&code_url=http%3A%2F%2Fstatic.apidog.ru%2Fexternal.min.js&js_code=");

			$result = json_decode($js);
			$code = $result->compiledCode;

			if (sizeof($result->errors) || !$code) {
				$errors = [];
				foreach ($result->errors as $i => $error) {
					$errors[] = [
						"type" => $error->type,
						"message" => $error->error,
						"line" => $error->lineno,
						"col" => $error->charno
					];
				};
				throwError(502, [ "errors" => $errors ]);
			};

			file_put_contents($fileTarget, $code);

			$s = $result->statistics;

			return [
				"result" => true,
				"stats" => [
					"original" => [
						"normal" => $s->originalSize,
						"gzip" => $s->originalGzipSize
					],
					"compressed" => [
						"normal" => $s->compressedSize,
						"gzip" => $s->compressedGzipSize
					]
				]
			];
		}
	};

	/**
	 * Настройки
	 */
	class Settings {
		public $bitmask;

		/**
		 * Получить настройки пользователя по его ID
		 * @param  int $userId Идентификатор пользователя
		 * @return array       Битовая маска настроек и язык
		 */
		static function getBitmask($userId) {
			return SQLquery("SELECT `bitmask`,`lang` FROM `settings` WHERE `userId` = '" . $userId . "' LIMIT 1", SQL_RESULT_ITEM);
		}

		/**
		 * Получить открытые сессии пользователя на сайте
		 * @return array Массив сессий
		 */
		static function getSessions() {
			$list = SQLquery("SELECT * FROM `auth` WHERE `user_id` = '" . userId . "' ORDER BY `date` DESC", SQL_RESULT_ITEMS);

			foreach ($list as $i => $session) {
				$list[$i] = new AuthSession($session);
			};

			return [
				"count" => sizeof($list),
				"items" => $list
			];
		}

		/**
		 * Изменение темы
		 * @param int $themeId Идентификатор темы
		 */
		static function setTheme($themeId) {
			$result = SQLquery("UPDATE `settings` SET `themeId` = '" . ((int) $themeId) . "' WHERE `userId` = '" . userId . "' LIMIT 1", SQL_RESULT_AFFECTED);
			return [ "result" => (boolean) $result ];
		}

		/**
		 * Получить сессию по идентификатору авторизации
		 * @deprecated
		 * @param  int $authId Идентификатор авторизации
		 * @return AuthSession Сессия
		 */
		static function getSessionById($authId) {
			return APIdogSession::getByAuthKey();
		}
	};


	/**
	 * Вывод данных в формате JSON
	 * @param  mixed $data Данные
	 */
	function output($data) {
		$callback = $_REQUEST["callback"];
		$json = json_encode(["response" => $data], JSON_UNESCAPED_UNICODE);
		header("Content-type: " . ($callback ? "text/javascript" : "application/json") . "; charset=utf-8");
		$data = ($callback ? $callback . "(" : "") . $json . ($callback ? ");" : "");
		header("Content-Length: " . mb_strlen($data));
		print $data;

		exit;
	};

	/**
	 * Вывод ошибки о том, что метод отключен
	 */
	function sendDeprecated() { return throwError(101); };

	/**
	 * Подключает модуль
	 * @deprecated
	 */
	function requireModule ($name) { include_once "./" . $name . "-engine.php"; };

	/**
	 * Проверяет, находится ли число $n в пределах [$min, $max]
	 */
	function toRange ($min, $n, $max) { return min($max, max($n, $min)); };































































	/**
	 * Обращение в поддержку
	 */
	class Ticket {
		public $ticketId;
		public $id;
		public $userId;
		public $date;
		public $title;
		public $actionId;
		public $categoryId;
		public $isPrivate;
		public $isNew;
		public $isBanned;
		public $canReply;
		public $canResponse;
		public $canEdit;
		public $canDelete;

		/**
		 * Проверка, заблокирован ли текущий пользователь
		 * @return boolean [description]
		 */
		static function isBlocked() {
			global $ssBlockedUsers;
			return in_array(userId, $ssBlockedUsers);
		}

		public function __construct ($t) {
			$this->ticketId = (int) $t["ticketId"];
			$this->id = dechex($this->ticketId);
			$this->userId = (int) $t["userId"];
			$this->date = (int) $t["date"];
			$this->title = $t["title"];
			$this->isNew = (boolean) !$t["isRead"];
			$this->actionId = (int) $t["actionId"];
			$this->categoryId = (int) $t["categoryId"];
			$this->isPrivate = (boolean) $t["isPrivate"];
			$this->isBanned = (boolean) Ticket::isBlocked();
			$this->canReply = (boolean) !$this->isBanned && $this->actionId != 18;
			$this->canResponse = (boolean) getAdmin();
			$this->canEdit = (boolean) ((getAdmin(userId) || $this->userId == userId) && time() - $this->date < 3 * 24 * 60 * 60);
			$this->canDelete = (boolean) (getAdmin(userId) || $this->userId == userId);
		}

		static function parse ($data, $isArray = false) {
			if ($isArray) {
				foreach ($data as $i => $item) {
					$data[$i] = new Ticket($item);
				};
				return $data;
			};

			return new Ticket($data);
		}

		/**
		 * Получение тикета по его идентификатору
		 * @param  int $ticketId Идентификатор тикета
		 * @return Ticket        Тикет
		 */
		static function getById($ticketId = 0) {
			if (!$ticketId) {
				return;
			};

			return new Ticket(APIdog::mysql("SELECT * FROM `supportTickets` WHERE `ticketId` = '$ticketId' LIMIT 1", 1));
		}

		/**
		 * Редактирование тикета
		 * @param  string  $title      Название
		 * @param  integer $categoryId Идентификатор категории
		 * @return int                 Результат
		 */
		public function edit($title, $categoryId = 0) {
			$ticketId = $this->ticketId;
			if (!$this->canEdit)
				throwError(23);
			return APIdog::mysql("UPDATE `supportTickets` SET `title` = '$title', `categoryId` = '$categoryId' WHERE `ticketId` = '$ticketId' LIMIT 1", 5);
		}

		public function delete ()
		{
			$ticketId = $this->ticketId;
			if (!$this->canDelete)
				throwError(24);

			APIdog::mysql("DELETE FROM `supportTickets` WHERE `ticketId` = '$ticketId'", 5);
			APIdog::mysql("DELETE FROM `supportComments` WHERE `ticketId` = '$ticketId'", 5);
			return true;
		}

		public function setRead ($state = true) {
			$state = (int) $state;
			return APIdog::mysql("UPDATE `supportTickets` SET `isRead` = $state WHERE `ticketId` = '{$this->ticketId}'");
		}

		// Deprecated
		public function setType ($t) { return $this->setAction($t); }

		public function setAction ($actionId)
		{
			return APIdog::mysql("UPDATE `supportTickets` SET `actionId` = '$actionId' WHERE `ticketId` = '{$this->ticketId}'", 5);
		}

		/**
		 * Comments
		 */

		public function getComments ($count = 20, $offset = 0)
		{
			$ticketId = $this->ticketId;
			$list = APIdog::mysql("SELECT * FROM `supportComments` WHERE `ticketId` = '$ticketId' AND `deleted` = '0' ORDER BY `commentId` ASC LIMIT $offset, $count", 2);
			$count = (int) APIdog::mysql("SELECT COUNT(*) FROM `supportComments` WHERE `ticketId` = '$ticketId' LIMIT 1", 3);
			foreach ($list as $i => $comment)
				$list[$i] = new Comment($comment);
			return [
				"count" => $count,
				"items" => $list
			];
		}

		public function getComment ($commentId)
		{
			$q = getAdmin() ? "" : " AND `userId`='" . userId . "'";
			$test = APIdog::mysql("SELECT * FROM `supportComments` WHERE `ticketId` = '{$this->ticketId}' AND `commentId` = '$commentId' $q LIMIT 1", 1);
			if (!$test)
				throwError(23);

			return new Comment($test);
		}

		public function addComment ($text, $actionId, $attachmentId)
		{
			$time = time();
			$commentId = APIdog::mysql("INSERT INTO `supportComments` (`ticketId`, `text`, `userId`, `date`, `actionId`, `attachments`) VALUES ('" . $this->ticketId . "', '" . $text . "', '" . userId . "', '" . $time . "', '" . $actionId . "', '" . $attachmentId . "')", 4);
			$this->setAction($actionId);
			return $commentId ? Comment::getById($commentId) : false;
		}

		public static function getAgents ($comments)
		{
			$agents = [];
			$already = [];
			foreach ($comments as $comment)
			{
				if ($comment->userId < 0 && !in_array($comment->userId, $already))
				{
					$agents[] = $GLOBALS["Agents"][-$comment->userId];
					$already[] = $comment->userId;
				};
			};
			return $agents;
		}

		public function checkPrivateAccess ()
		{
			if (!$this->isPrivate || getAdmin() || $this->isPrivate && $this->userId == userId)
				return;
			throwError(26);
		}

		public function updateActionId () {
			$last = APIdog::mysql("SELECT `actionId` FROM `supportComments` WHERE `ticketId` = '" . $this->ticketId . "' AND `deleted` = '0' ORDER BY `commentId` DESC LIMIT 1", 1);
			$this->setAction($last["actionId"]);
			return (int) $last["actionId"];
		}
	}

	class Comment
	{
		public $ticketId;
		public $commentId;
		public $userId;
		public $date;
		public $dateEdited;
		public $type;
		public $text;
		public $actionId;
		public $attachments;
		public $canEdit;
		public $canDelete;

		public function __construct ($c)
		{
			$isAdmin = getAdmin();
			$curAdmin = getAdmin($c["userId"]);
			$this->ticketId = (int) $c["ticketId"];
			$this->commentId = (int) $c["commentId"];
			$this->userId = (int) ($curAdmin && !$GLOBALS["Agents"][$curAdmin]["isModer"] ? -$curAdmin : $c["userId"]);
			$this->date = (int) $c["date"];
			$this->dateEdited = (int) $c["updated"];
			$this->actionId = (int) $c["actionId"];
			$this->text = $c["text"];
			if ($c["attachments"]) {
				$items = explode(",", $c["attachments"]);
				foreach ($items as $i => $item) {
					$items[$i] = Attachment::getById($item);
				};
				$this->attachments = $items;
			}
			if ($isAdmin)
				$this->canMark = true;
			if ($c["userAgent"])
				$this->userAgent = $c["userAgent"];
			$this->canEdit = (($isAdmin && $this->userId < 0) || $this->userId == userId) && time() - $this->date < 5 * 60 * 60;
			$this->canDelete = ($isAdmin || $this->userId == userId);
		}

		static function parse ($data, $isArray = false)
		{
			if ($isArray)
			{
				foreach ($data as $i => $item)
				{
					$data[$i] = new Comment($item);
				}
				return $data;
			}
			return new Comment($data);
		}

		static function getById ($commentId)
		{
			$data = APIdog::mysql("SELECT * FROM `supportComments` WHERE `commentId` = '$commentId' LIMIT 1", 1);
			return $data ? new Comment($data) : false;
		}

		public function edit ($text)
		{
			if (!$this->canEdit)
			{
				throwError(23);
			};


			$time = time();
			$query = APIdog::mysql("UPDATE `supportComments` SET `text` = '$text', `updated` = '$time' WHERE `commentId` = '{$this->commentId}' AND `ticketId`='{$this->ticketId}' LIMIT 1", 5);
			return (boolean) $query;
		}

		public function delete ()
		{

			if (!$this->canDelete)
			{
				throwError(23);
			};

			$t = $this->ticketId;
			$c = $this->commentId;
			$n = time();

			APIdog::mysql("UPDATE `supportComments` SET `deleted` = '$n' WHERE `commentId` = '$c' LIMIT 1");
			if (!APIdog::mysql("SELECT COUNT(*) FROM `supportComments` WHERE `ticketId`='$t' AND `deleted`='0' LIMIT 1", 3))
			{
				APIdog::mysql("DELETE FROM `supportTickets` WHERE `ticketId`='$t'");
				APIdog::mysql("DELETE FROM `supportComments` WHERE `ticketId`='$t'");
			};
			return true;
		}

		public function restore ()
		{

			if (!$this->canDelete)
			{
				throwError(23);
			};

			$t = $this->ticketId;
			$c = $this->commentId;


			return (boolean) APIdog::mysql("UPDATE `supportComments` SET `deleted` = '0' WHERE `commentId` = '$c'");
		}
	}

	class Attachment
	{
		public $attachmentId;
		public $userId;
		public $date;
		public $url;
		private $image;

		public function __construct ($a)
		{
			$this->attachmentId = (int) $a["attachmentId"];
			$this->userId = (int) $a["userId"];
			$this->date = (int) $a["date"];
			$this->key = $a["key"];
			$this->url = "http://apidog.ru/api-v2.php?method=support.getAttachment&attachmentId=" . $this->attachmentId . "&key=" . $this->key . "&authKey=" . $GLOBALS["authKey"];
			$this->image = $a["image"];
		}

		public function getRealImage () {
			return $this->image;
		}

		static function getById ($attachmentId)
		{
			return new Attachment(APIdog::mysql("SELECT * FROM `supportAttachments` WHERE `attachmentId` = '$attachmentId'", 1));
		}

		static function getUploadURL ($userId)
		{
			return "http://apidog.ru/api-v2.php?method=support.uploadAttachment&data=" . base64_encode($userId . "|" . time() . "|" . md5($GLOBALS["authKey"]));
		}

		static function uploadImage ($file)
		{

			if (!$file)
				throwError(21);

			if ($file["size"] > 2.5 * MB)
				throwError(27);

			if (!($link = APIdog::uploadImage($file["tmp_name"])) || !$link["url"])
				throwError(27);

			$link = $link["url"];

			$link = escape($link);
			$date = time();
			$hash = md5($date . userId);
			$attachmentId = APIdog::mysql("INSERT INTO `supportAttachments` (`userId`, `image`, `key`, `date`) VALUES ('" . userId . "', '" . $link . "', '" . $hash . "', '" . $date . "')", 4);
			$attachment = Attachment::getById($attachmentId);
			return $attachment;
		}
	}




	class Blog {
		public $db;
		public function __construct() { }

		/**
		 * Возвращает записи из блога
		 * @param  int $offset Сдвиг выборки
		 * @return array       Массив с count и items
		 */
		public function getTimeline($offset = 0) {
			$offset = (int) $offset;
			$items = SQLquery("SELECT * FROM `blog` ORDER BY `postId` DESC LIMIT $offset,30", SQL_RESULT_ITEMS);
			$items = $items ? $items : [];
			return ["count" => sizeOf($items), "items" => $items];
		}

		/**
		 * Добавление записи в блог
		 * @param string $title   Заголовок
		 * @param string $text    Текст
		 * @param int    $adminId ID пользователя
		 */
		public function addPost($title, $text, $adminId) {
			$id = SQLquery("INSERT INTO `blog` (`date`, `title`, `text`, `adminId`) VALUES ('". time(). "','". escape($title) . "','" . escape($text) . "','" . ((int) $adminId) . "'", SQL_RESULT_INSERTED);

			return $id;
		}

		/*public function editPost ($postId, $title, $text) {
			return $this->db->update()->table("blog")->set([
				"title" => $title,
				"text" => $text
			])->where(["postId", $postId])->execute();
		}*/

		/**
		 * Удаление записи из блога
		 * @param  int $postId Идентификатор поста
		 */
		public function deletePost($postId) {
			return SQLquery("DELETE FROM `blog` WHERE `postId`='" . ((int) $postId) . "' LIMIT 1");
		}

		/**
		 * Возвращает пост из блога по идентификатору
		 * @param  int  $postId Идентификатор поста
		 * @return array        Пост
		 */
		public function getPost($postId) {
			$post = SQLquery("SELECT * FROM `blog` WHERE `postId` = '" . ((int) $postId) . "' LIMIT 1", SQL_RESULT_ITEM);

			if ($post) {
				$post["views"] = $this->viewPost($postId);
			};

			return $post ? ["post" => $post] : null;
		}


		private function viewPost($postId) {
			$postId = (int) $postId;
			SQLquery("INSERT INTO `blogViews` (`postId`, `userId`) VALUES ('" . $postId . "', '" . userId . "')", 0);
			$count = SQLquery("SELECT COUNT(*) FROM `blogViews` WHERE `postId`='" . $postId . "' LIMIT 1", SQL_RESULT_COUNT);

			return $count;
		}
	};


	class Theme
	{
		public $themeId;
		public $title;
		public $file;
		public $updated;
		public $installs;
		public $authorId;
		public $isPrivate;
		public $version;
		public $changelog;
		private $_isAuthor;

		public function __construct ($theme)
		{
			if (is_int($theme)) {
				$theme = (int) $theme;
				$theme = APIdog::mysql("SELECT * FROM `themes` WHERE `themeId` = '$theme' LIMIT 1", 1);
			}

			$this->themeId = (int) $theme["themeId"];
			$this->authorId = (int) $theme["authorId"];
			$this->file = $theme["file"];
			$this->title = $theme["title"];
			$this->updated = (int) $theme["updated"];
			$this->version = $theme["version"];
			$this->installs = (int) $theme["installCount"];
			$this->changelog = $theme["changelog"];
			$this->isPrivate = (boolean) $theme["isPrivate"];

			if ($theme["authorId"] == userId)
			{
				$this->_isAuthor = true;
			};
		}

		static function create ($title, $content, $isPrivate = false, $version = "", $changelog = "")
		{
			$updated = time();
			$file = userId . "." . $updated . ".css";
			$userId = userId;

			$fh = fopen("./styles/" . $file, "w+");
			fwrite($fh, $content);
			fclose($fh);

			$themeId = APIdog::mysql("INSERT INTO `themes` (`title`, `file`, `updated`, `isPrivate`, `authorId`, `version`, `changelog`) VALUES ('$title', '$file', '$updated', '$isPrivate', '$userId', '$version', '$changelog')", 4);

			return new Theme($themeId);
		}

		static function floodControl ()
		{
			$check = APIdog::mysql("SELECT COUNT(*) FROM `themes` WHERE `authorId` = " . userId, 3);
			return $check > 5;
		}

		public function isAuthor ()
		{
			return $this->_isAuthor;
		}

		public function open ()
		{
			$content = file_get_contents("./styles/" . $this->file);
			$this->content = $content;
			return $this;
		}

		public function getAuthorId ()
		{
			return $this->authorId;
		}

		public function getContent ()
		{
			return $this->content ? $this->content : $this->open()->content;
		}

		public function getInstalledUsersCount ()
		{
			$n = APIdog::mysql("SELECT COUNT(*) FROM `settings` WHERE `themeId` = '" . $this->themeId . "'", 3);
			$this->installs = (int) $n;
			return $n;
		}

		public function updateInstalledUsersCount () {
			$n = APIdog::mysql("SELECT COUNT(*) FROM `settings` WHERE `themeId` = '" . $this->themeId . "'", 3);
			return APIdog::mysql("UPDATE `themes` SET `installCount` = '" . $n . "' WHERE `themeId` = '" . $this->themeId . "' LIMIT 1", 5);
		}

		static function getList ($sort = 0, $onlyMe = true)
		{
			$condition = !$onlyMe ? "`isPrivate` = 0 OR `authorId` = '" . userId . "'" : "`authorId` = '" . userId . "'";
			$themes = APIdog::mysql("SELECT * FROM `themes` WHERE " . $condition . " ORDER BY `themeId` DESC LIMIT 150", 2);
			$count = APIdog::mysql("SELECT COUNT(*) FROM `themes` WHERE" . $condition, 3);
			$items = [];

			foreach ($themes as $i => $item)
			{
				$t = new Theme($item);
				$items[] = $t;
			};
			switch ($sort) {
				case 1: // by update
					usort($items, function ($a, $b) {
						return $a->updated > $b->updated ? -1 : $a->updated == $b->updated ? 0 : 1;
					});
					break;

				case 2: // by installs
					usort($items, function ($a, $b) {
						return $a->installs > $b->installs ? -1 : $a->installs == $b->installs ? 0 : 1;
					});
					break;
			};

			return ["count" => $count, "items" => $items];
		}

		public function edit ($title, $content, $isPrivate, $version, $changelog)
		{
			$time = time();

			$sql = APIdog::mysql("UPDATE `themes` SET `title` = '" . $title . "',`updated`='" . $time . "',`isPrivate`='" . ((int) $isPrivate) . "',`version`='" . $version . "',`changelog`='" . $changelog . "' WHERE `themeId` = '" . $this->themeId . "' LIMIT 1", 5);

			$fh = fopen("./styles/" . $this->file, "w+");
			$file = fwrite($fh, $content);
			fclose($fh);
			return [
				"result" => true
			];
		}

		public function delete ()
		{
			unlink("./styles/" . $this->file);
			APIdog::mysql("DELETE FROM `themes` WHERE `themeId` = '" . $this->themeId . "' LIMIT 1", 4);
			return ["result" => true];
		}

		static function isValid ($code)
		{
			$roundStart = substr_count($code, "(");
			$roundEnd = substr_count($code, ")");

			$rectStart = substr_count($code, "[");
			$rectEnd = substr_count($code, "]");

			$figureStart = substr_count($code, "{");
			$figureEnd = substr_count($code, "}");

			return !($roundStart != $roundEnd || $rectStart != $rectEnd || $figureStart != $figureEnd || !$figureStart || !$figureEnd);
		}

		static function getById ($themeId)
		{
			if (is_array($themeId))
			{
				$data = [];
				foreach ($themeId as $id)
				{
					if ($id <= 0)
					{
						continue;
					};

					$item = new Theme($id);

					if ($item->themeId)
					{
						$data[] = $item;
					};
				};
				return $data;
			};
			return new Theme($themeId);
		}

		public function getUsers ()
		{
			$userIds = APIdog::mysql("SELECT `userId` FROM `settings` WHERE `themeId` = '" . $this->themeId . "'", 2);

			return [
				"count" => $this->usersCount,
				"items" => array_map(function ($i) {
					return intVal($i["userId"]);
				}, $userIds)
			];
		}
	}

	class Poll
	{

		static function getState ($userId)
		{
			return APIdog::mysql("SELECT COUNT(*) FROM `poll` WHERE `userId` = '" . ((int) $userId) . "'", 3) < 1;
		}

		static function getCount ()
		{
			return APIdog::mysql("SELECT COUNT(*) FROM `poll`", 3);
		}

		static function getFieldNames ()
		{
			return [
				["name" => "rate", "required" => true, "label" => "Какую оценку Вы поставите нашему сайту?", "type" => 1, "items" =>
					array_map(function ($i) { return ["value" => $i, "label" => $i]; }, range(10, 1))
				],
				["name" => "comment", "required" => false, "label" => "Опишите своими словами сайт (необяз.)", "type" => 0],
				["name" => "whereKnow", "required" => true, "label" => "Откуда Вы узнали о сайте?", "type" => 0],
				["name" => "isClaim", "required" => false, "label" => "Есть ли претензии по работе сайта?  (необяз.)", "type" => 0],
				["name" => "isClaimSupport", "required" => false, "label" => "Есть ли претензии к поддержке? (необяз.)", "type" => 0],
				["name" => "timeOfUsing", "required" => true, "label" => "Время использования сайта", "type" => 1, "items" => [
					["value" => 1, "label" => "менее месяца"],
					["value" => 2, "label" => "1-2 месяца"],
					["value" => 3, "label" => "3-6 месяцев"],
					["value" => 4, "label" => "полгода-год"],
					["value" => 5, "label" => "1-2 года"],
					["value" => 6, "label" => "с основания проекта (2012 г.)"]
				]],
				["name" => "suggestions", "required" => false, "label" => "Ваши предложения для проекта (необяз.)", "type" => 0],
				["name" => "isTrust", "required" => false, "label" => "Доверяете ли Вы сайту?", "type" => 2],
				["name" => "getInfoSource", "required" => true, "label" => "Где бы Вы хотели получать новости/инфо о сайте? (блог/группа/Телеграм/Твиттер/вообще не хотел бы/пр.)", "type" => 0],
				["name" => "other", "required" => false, "label" => "Что-то от себя / пожелания (необяз.)", "type" => 0]
			];
		}

		static function addAnswer ($userId, $params)
		{
			if (!Poll::getState($userId)) {
				throwError(121);
			};

			$result = APIdog::mysql("INSERT INTO `poll` (`userId`,`date`,`rate`,`comment`,`whereKnow`,`isClaim`,`isClaimSupport`,`timeOfUsing`,`suggestions`,`isTrust`,`other`) VALUES (" . $userId . "," . time() . ",'" . escape($params["rate"]) . "','" . escape($params["comment"]) . "','" . escape($params["whereKnow"]) . "','" . escape($params["isClaim"]) . "','" . escape($params["isClaimSupport"]) . "','" . escape($params["timeOfUsing"]) . "','" . escape($params["suggestions"]) . "','" . escape($params["isTrust"]) . "','" . escape($params["other"]) . "')", 4);
			return [
				"result" => $result,
				"answers" => [
					"count" => Poll::getCount()
				]
			];
		}

		static function viewedInfo ($userId)
		{
			APIdog::mysql("UPDATE `poll` SET `viewed`=1 WHERE `userId`=" . $userId . " LIMIT 1");
			return 1;
		}

	};














