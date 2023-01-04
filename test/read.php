<?php


error_reporting(0);
date_default_timezone_set('Asia/Tokyo');


include("./setting.php");

unset($_ENV);
$_ENV['URL'] = GenerateURL();
$_ENV['sitekey'] = SITEKEY;

if($_GET['bbs'] and $_GET['key']){
	$_ENV['bbs'] = $_GET["bbs"];
	$_ENV['key'] = $_GET["key"];
}
elseif ($_SERVER['PATH_INFO']){
	list(, $_ENV['bbs'], $_ENV['key'], $_ENV['option']) = explode("/", $_SERVER['PATH_INFO']);
}
elseif($_GET['PATH_INFO']){
	list(, $_ENV['bbs'], $_ENV['key'], $_ENV['option']) = explode("/", $_GET['PATH_INFO']);
}
else { PrintError("不正なパラメータです。"); }

if(preg_match("/[\.\/]/", $_ENV['bbs'])){ PrintError("不正なキーです。"); }
if(preg_match("/[\.\/]/", $_ENV['key'])){ PrintError("不正なキーです。"); }

PrintThread();
exit;




function PrintThread(){
	$i = 0;
	$time = $_SERVER['REQUEST_TIME'];

	$file = "../{$_ENV['bbs']}/dat/{$_ENV['key']}.dat";
	if(!is_file($file)){
		$key4 = substr($_ENV['key'], 0, 4);
		$key5 = substr($_ENV['key'], 0, 5);
		$file = "../{$_ENV['bbs']}/kako/$key4/$key5/{$_ENV['key']}.dat";
		if(is_file($file)){ $_ENV['readonly'] = "readonly"; }
		else { PrintError("スレッドが見つかりませんでした。"); }
	}

	$fp = fopen($file, 'rb');
	while(($line = fgets($fp)) !== false){
		$i++;
		
		list($name, $url, $date, $id, $text, $subject) = explode("<>", $line);
		if($date == "あぼーん"){ continue; }
		$text = preg_replace("/\&gt;\&gt;(\d+)/", "<a href=\"#res$1\" class=\"anchor\">&gt;&gt;$1</a>", $text);
		$text = preg_replace("/(https?:\/\/[a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\-\_\.\!\~\*\'\(\)\%\#]+)/", "<a href=\"\\1\" target=\"_blank\" rel=\"nofollow\">\\1</a>", $text);
		if ($url) {
			if (preg_match("/^https?:\/\//i", $url)){ $namestring = "<a href=\"$url\" target=\"_blank\" rel=\"nofollow\">$name</a>"; }
			else { $namestring = $name; }
		}
		else{
			$namestring = $name;
		}
		if ($i == 1){ $_ENV['subject'] = rtrim($subject); }
		
		$html = THREAD_HTML;
		$html = str_replace('[[no]]', $i, $html);
		$html = str_replace('[[FROM]]', $name, $html);
		$html = str_replace('[[FROM_String]]', $namestring, $html);
		$html = str_replace('[[date]]', $date, $html);
		$html = str_replace('[[id]]', $id, $html);
		$html = str_replace('[[mail]]', $url, $html);
		$html = str_replace('[[MESSAGE]]', $text, $html);

		$_ENV['thread'] .= "$html\n";
	}

	$_ENV['cookie_name'] = htmlspecialchars($_COOKIE["NAME"]);
	$_ENV['cookie_mail'] = htmlspecialchars($_COOKIE["MAIL"]);


	if ($i >= MAX_RES) { $_ENV['readonly'] = "readonly"; }

	header("Content-type: text/html; charset=shift_jis");

	PrintTemplate("../{$_ENV['bbs']}/thread.html");

}


function PrintError($str){
	header("Cache-Control: no-cache");
	header("Content-type: text/html; charset=shift_jis");

	print "<html><!-- 2ch_X:error --><head><title>ＥＲＲＯＲ！</title></head>";
	print "<body><b>ＥＲＲＯＲ：$str</b>";
	print "<br><a href=\"javascript:history.back()\">戻る</a></body></html>";

	exit;
}


function GenerateURL(){
	if($_SERVER['HTTPS']=="on"){ $protocol = "https://"; }
	else{ $protocol = "http://"; }

	$request_uri = preg_replace("/\/test\/.*/", "/", $_SERVER['REQUEST_URI']); // /test/以下を削除
	
	$url = $protocol . $_SERVER["HTTP_HOST"] . $request_uri;
	return $url;
}


function PrintTemplate($file){
	print preg_replace_callback('/(?:<\!--)?\[\[([^\]]+)\]\](?:-->)?/', 'CB', file_get_contents($file));
}
function CB($matches){
	return $_ENV["$matches[1]"];
}