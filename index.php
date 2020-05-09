<?php

	class UserKnowledge {
		
		private static $hlIdValues = array(

			1 => 'Химия',
			2 => 'Алгебра',
			3 => 'География',
			4 => 'Литература',
			5 => 'Геометрия',
			6 => 'Русский язык',
			7 => 'Физкультура'

		);

		/** Метод выводит список пользователей (как и всех в выборке, так и с использованием пагинации).
		 * 
		 * @param array $arPagination Массив с параметрами пагинации (page и limit). Параметр необязателен. Выведет всё, если ничего не передать или передать пустой массив. "page" в массиве должен содержать числовое значение (по умолчанию 1). "limit" в массиве должен содержать числовое значение (по умолчанию 20).
		 * @return array либо возвращает false (boolean) при отсутствии результатов
		 */
		public static function getArray($arPagination = array()){
			
			$userList = array();
			
			// формируем переменную с параметрами выборки для d7 getList
			$arGetListParameters = array(

				'select' => array('ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'PERSONAL_PHONE', 'PERSONAL_GENDER', 'WORK_PHONE', 'WORK_COMPANY', 'DATE_REGISTER', 'UF_KNOWLEDGE'), 

				'filter' => array('=ACTIVE' => true),
				'order' => array('ID' => 'ASC')

			);

			// если параметры пагинации передаются в метод, то добавляем их в параметры выборки для d7 getList
			if(count($arPagination) > 0){
				
				// проверяем параметр 'page' по умолчанию
				if(!array_key_exists('page', $arPagination['page']) && !is_numeric($arPagination['page'])){
					$arPagination['page'] = 1; // устанавливаем 20 по умолчанию
				}

				// проверяем параметр 'limit' по умолчанию
				if(!array_key_exists('limit', $arPagination['limit']) && !is_numeric($arPagination['limit'])){
					$arPagination['limit'] = 20; // устанавливаем 20 по умолчанию
				}

				$arGetListParameters = array_merge($arGetListParameters, array(

					'limit' => $arPagination['limit'],
					'offset' => $arPagination['page']*$arPagination['limit']-1

				));
			}

			// формируем запрос к БД для получения списка активных пользователей
			$userListDB = \Bitrix\Main\UserTable::getList($arGetListParameters);

			if($userListDB->getSelectedRowsCount() == 0){
				return false; // пользователей не обнаружено
			}

			while($arUser = $userListDB->fetch()){

				$arUser['MINUTE_AFTER_REGISTRATION'] = floor(abs($arUser['DATE_REGISTER']->getTimestamp() - strtotime(date('Y-m-d H:i:s')))/60); // считаем кол-во минут с момента регистрации (по текущую дату) 

				unset($arUser['DATE_REGISTER']); // удаляем из массива элемент, которого не должно быть в выходных данных

				$userList[] = $arUser;

			}

			return $userList;

		}

		/** Метод формирует CSV файл со списком активных пользователей (скачивается в браузере пользователем)
		 * 
		 * @param type $arPagination Массив с параметрами пагинации (page и limit). Параметр необязателен. Выведет всё, если ничего не передать или передать пустой массив. "page" в массиве должен содержать числовое значение (по умолчанию 1). "limit" в массиве должен содержать числовое значение (по умолчанию 20).
		 */
		public static function getCSV($arPagination = array()){
			
			// формируем заголовки для вывода содержимого CSV файла и его скачивания браузером
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename=userList_'.date('Y-m-d H:i:s').'.csv');

			// получаем список пользователей
			$userList = self::getArray($arPagination);

			$handle = fopen('php://output', 'w');
			
			$headers = array('ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'PERSONAL_PHONE', 'PERSONAL_GENDER', 'WORK_PHONE', 'WORK_COMPANY', 'MINUTE_AFTER_REGISTRATION', 'UF_KNOWLEDGE');

			fputcsv($handle, $headers); // заголовки CSV файла (первая строка)

			// формируем содержимое CSV файла
			foreach($userList as $arUser) {

				$knowledgeValues = array();

				foreach($arUser['UF_KNOWLEDGE'] as $value){
					$knowledgeValues[] = self::$hlIdValues[$value];
				}

				$arUser['UF_KNOWLEDGE'] = implode(', ', $knowledgeValues); // устанавливаем соответствие ключей к значениям

				$row = array();

				foreach($headers as $value){
					$row[$value] = $arUser[$value];
				}

				// дописываем сформированную строку в генерируемый CSV
				fputcsv($handle, $row);

			}

			fclose($handle);

			exit;

		}

		public static function getXML($arPagination = array()){

			$dom = new DOMDocument('1.0', 'utf-8');

			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;

			// создаём вложенный массив "users"
			$users = $dom->createElement('users');

			// получаем список пользователей
			$userList = self::getArray($arPagination);

			foreach($userList as $arUser){

				// создаём XML структуру массива второго уровня
				$user = $dom->createElement('user');

				$users->appendChild($user);

				// создаём и заполняем элементы массива второго уровня
				$user->appendChild($dom->createElement('id', $arUser['ID']));
				$user->appendChild($dom->createElement('login', $arUser['LOGIN']));
				$user->appendChild($dom->createElement('name', $arUser['NAME']));
				$user->appendChild($dom->createElement('lastName', $arUser['LAST_NAME']));
				$user->appendChild($dom->createElement('secondName', $arUser['SECOND_NAME']));
				$user->appendChild($dom->createElement('minuteAfterRegistratuion', $arUser['MINUTE_AFTER_REGISTRATION']));

				// формируем массив третьего уровня
				$knowlegdes = $dom->createElement('knowlegdes');

				$user->appendChild($knowlegdes);

				foreach($arUser['UF_KNOWLEDGE'] as $value){ // self::$hlIdValues[$value]

					$knowlegde = $dom->createElement('knowlegde', self::$hlIdValues[$value]);

					// добавляем атрибут "id"
					$attr = $dom->createAttribute('id');

					$attr->value = $value;

					$knowlegde->appendChild($attr);

					// добавляем в структуру XML
					$knowlegdes->appendChild($knowlegde);

				}

			}

			$dom->appendChild($users);

			$xml = $dom->saveXML();

			header('Content-Type: application/octet-stream');
			header('Accept-Ranges: bytes');
			header('Content-Disposition: attachment; filename=xmlFile.xml');  

			// выводим результат
			echo $xml;

		}

	}

	$init = new UserKnowledge();

//	// получаем активных пользователей на второй странице в размере 30 результатов
//	print_r($init->getArray(array(
//		'page' => 2,
//		'limit' => 30
//	)));

//	// получаем всех активных пользователей
//	print_r($init->getArray());

//	// формируем и получаем CSV файл (скачиваем в браузере) с ограниченным списком пользователей (2-я страница, 30 результатов)
//	$init->getCSV(array(
//		'page' => 2,
//		'limit' => 30
//	));

//	// формируем и получаем CSV файл (скачиваем в браузере) с полным списком активных пользователей
//	$init->getCSV();

//	// формируем и получаем XML файл (скачиваем в браузере) с ограниченным списком пользователей (2-я страница, 30 результатов)
//	$init->getXML(array(
//		'page' => 2,
//		'limit' => 30
//	));

//	// формируем и получаем XML файл (скачиваем в браузере) с полным списком активных пользователей
//	$init->getXML();