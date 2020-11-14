<?php
/*
 * config
 */

define('DB_USER','');
define('DB_PASSWD','');
define('DB_HOST','localhost');
define('DB_TYPE','mysql');
if (empty($argv[1])){
	echo '需要指定原始資料庫'."\n";
	return;
}
else
	define('DB_NAME_ORIGIN',$argv[1]);

if (empty($argv[2]))
{
	echo '需要指定目地資料庫'."\n";
	return;
}
else
	define('DB_NAME_DEST',$argv[2]);

$dbh = new PDO(DB_TYPE.':host='.DB_HOST.';dbname='.DB_NAME_ORIGIN, DB_USER, DB_PASSWD);

/*
 * 檢查資料庫是否存在
 */
$sth = $dbh->prepare('show databases like :db');
$sth->bindParam(':db',$dbname);
$dbnames = [ DB_NAME_ORIGIN , DB_NAME_DEST];
foreach($dbnames as $dbname){
	$sth->execute();
	if ($sth->rowCount() == 0){
		echo 'Database '.$dbname.' not found';
		return;
	};

}
/*
 * 創建不鄉在的table
 */
$origin = getAllTable(DB_NAME_ORIGIN);
$dest = getAllTable(DB_NAME_DEST);
$diffTable = (array_diff($origin,$dest));
foreach($diffTable as $table){
	echo $sql = "create table ". DB_NAME_DEST .".{$table} like ". DB_NAME_ORIGIN. ".{$table}";
	echo "\n";
	$dbh->query($sql) ;
}

/*
 * 變更缺少的欄位
 */

foreach($origin as $table){
	$msg ='';
	$msg .= "處理 ".$table." 中.";
	$origin = json_encode(getTable(DB_NAME_ORIGIN,$table),true);
	$dest = json_encode(getTable(DB_NAME_DEST,$table),true);
	if ($origin === $dest) { 
	}else{
		echo $msg . " : 二者不一致，處理中\n";
		$origin = changeFormat(json_decode($origin,true));
		$dest = changeFormat(json_decode($dest,true));
		foreach($origin as $name => $field){
			if( !array_key_exists($name,$dest)){
				$dbh->query('use '.DB_NAME_DEST);
				if (empty($field['Default'])) $field['Default'] = 'null';
				echo $sql = "alter table $table add {$field['Field']} {$field['Type']} Default {$field['Default']} ";
				echo "\n";
				$sth = $dbh->prepare($sql);
				$sth->execute();
				var_dump($sth->errorInfo());
			}
		}
		foreach($dest as $name => $field){
			if( !array_key_exists($name,$origin)){
			echo 'aa';
				$dbh->query('use '.DB_NAME_DEST);
				if (empty($field['Default'])) $field['Default'] = 'null';
				echo $sql = "alter table $table drop  {$field['Field']}";
				echo "\n";
				$sth = $dbh->prepare($sql);
				$sth->execute();
				var_dump($sth->errorInfo());
			}
		}
	}
}

function changeFormat($col){
	$data = [];
	foreach($col as $value){
		$key = $value['Field'];
		$data[$key] = $value;
	}
	asort($data);
	return $data;
}
function getTable($db,$table){
	global $dbh;
	$dbh->query('use ' .$db);

	$q = $dbh->query("desc $table");
	$sth = $dbh->prepare("desc $table");
	$sth->execute();
	return $sth->fetchAll(PDO::FETCH_ASSOC);
}

/*
 * 檢查Table 是否一致
 */

function getAllTable($db){
	global $dbh;
	$tables = [];
	$dbh->query('use ' . $db);
	$sth = $dbh->prepare('show tables');
	$sth->execute();
	if($sth->rowCount() > 0){
		while($r = $sth->fetch(PDO::FETCH_ASSOC) ){
			$tables[] = ($r['Tables_in_'.$db]);
		}
	}
	return $tables;
}
