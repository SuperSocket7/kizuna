<?php

error_reporting(0);
date_default_timezone_set('Asia/Tokyo');

include("./setting.php");
if(!$_POST['bbs']){ RecieveRawPost(); } //クライアントにより$_POSTにデータが入らないバグ対策


//メイン処理
GoToAdmin();
CheckBBS();
CheckPost();
if($_POST['key'])
		{ WriteRes(); }
		else
		{ WriteThread(); }
PrintOK();
exit;
//メイン処理 おわり 以下サブルーチン


//管理モードに分岐するかどうか
function GoToAdmin(){
	if(!in_array($_POST['FROM'], array('削除', '倉庫', '復帰', '2ch'))){ return; }
	if(!preg_match("/^#/", $_POST['mail'])){ return; }
	if(!$_POST['bbs']){ return; }

	include('./admin.php');
}


//板とスレッドの確認
function CheckBBS(){
	if(!$_POST['bbs']){ PrintError("不正なキーです"); }
	if(preg_match("/[\.\/]/", $_POST['bbs'])){ PrintError("不正なキーです"); }
	if(preg_match("/[\.\/]/", $_POST['key'])){ PrintError("不正なキーです"); }


	if(!is_file("../{$_POST['bbs']}/subject.txt")){ PrintError("そんな板ないです"); }
	if($_POST['key'] and !is_file("../{$_POST['bbs']}/dat/{$_POST['key']}.dat")){
		$key4 = substr($_POST['key'], 0, 4);
		$key5 = substr($_POST['key'], 0, 5);
		if(is_file("../{$_POST['bbs']}/kako/$key4/$key5/{$_POST['key']}.dat")){ PrintError("このスレッドにはもう書きこめません"); }
		else { PrintError("そんなスレッドないです"); }
	}
}


//規制処理もろもろ。作成中
function CheckPost(){
	if($_POST['MESSAGE'] == '') { PrintError("本文を入力してください"); }
	if($_POST['FROM'] == " ") { PrintError("名前欄を空白にすることはできません"); }
	if($_POST['MESSAGE'] == " ") { PrintError("本文を入力してください"); }
	if($_POST['subject'] == '' and !$_POST['key']){ PrintError("題名を入力してください"); }
	
	if(strlen($_POST['subject']) > MAX_SUBJECT){ PrintError("題名欄が大きすぎます"); }
	if(strlen($_POST['FROM'])    > MAX_FROM)   { PrintError("名前欄が大きすぎます"); }
	if(strlen($_POST['mail'])    > MAX_MAIL)   { PrintError("URL欄が大きすぎます"); }
	if(strlen($_POST['MESSAGE']) > MAX_MESSAGE){ PrintError("本文欄が大きすぎます"); }

	//reCapcha処理
	$recaptcha = htmlspecialchars($_POST["g-recaptcha-response"],ENT_QUOTES,'UTF-8');
	if($_POST['g-recaptcha-response'] == '') { PrintError("投稿できません"); }
	$resp = @file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={SECRETKEY}&response={$recaptcha}");
	$resp_result = json_decode($resp,true);

	if(intval($resp_result["success"]) !== 1) {
		return;
	} else {
		PrintError("投稿できません");
	}
}


//スレ立て
function WriteThread(){
	$_POST['key'] = $_SERVER['REQUEST_TIME'];

	//subject.txt：$subjectlistに全データ格納
	$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
	if(!$fp_subject){ PrintError("subject.txtが開けません"); }

	flock($fp_subject, LOCK_EX);
	while(!feof($fp_subject)){
		$subjectlist[] = fgets($fp_subject);
	}

	//DATファイルに書き込む
	$name    = GenerateName($_POST['FROM']);
	$mail    = GenerateMail($_POST['mail']);
	$date    = GenerateDate();
	$message = GenerateMessage($_POST['MESSAGE']);
	$subject = GenerateSubject($_POST['subject']);
	$id      = GenerateID();

	if(is_file("../{$_POST['bbs']}/dat/{$_POST['key']}.dat")){
		CloseFile($fp_subject);
		PrintError("もう一度投稿してください");
	}

	$fp_dat = fopen("../{$_POST['bbs']}/dat/{$_POST['key']}.dat", "wb+");
	if(!$fp_dat){
		CloseFile($fp_subject);
		PrintError("DATファイルが作成できません");
	}

	$dat_line = "$name<>$mail<>$date<> $id<> $message <>$subject\n";
	$sub_line = "{$_POST['key']}.dat<>$subject (1)\n";
	$match_count = substr_count("$dat_line$sub_line", "<>");
	if($match_count != 6){
		CloseFile($fp_subject, $fp_dat);
		PrintError("投稿できませんでした");
	}

	flock($fp_dat, LOCK_EX);
	fputs($fp_dat, $dat_line);
	CloseFile($fp_dat);

	//subject.txtに書き込む
	array_unshift($subjectlist, $sub_line);
	ftruncate($fp_subject,0);
	rewind($fp_subject);
	fputs($fp_subject, implode("", $subjectlist));
	CloseFile($fp_subject);
}


//レス書き込み
function WriteRes(){
	$subject_num = $dat_num = 0;
	//subject.txt：書き込もうとするスレを見つける＋$subjectに全データ格納
	$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
	if(!$fp_subject){ PrintError("subject.txtが開けません"); }

	flock($fp_subject, LOCK_EX);
	while(!feof($fp_subject)){
		$line = fgets($fp_subject);
		preg_match("/^(\d+)\.dat<>(.+)/", $line, $matched);
		$now_key = $matched[1];
		if ($_POST['key'] == $now_key) {
			$save_subject_num  = $subject_num;
		}
		$subject[] = $line;
		$subject_num++;
	}

	//DATファイル：$datに全データ格納
	$fp_dat = fopen("../{$_POST['bbs']}/dat/{$_POST['key']}.dat", "rb+");
	if(!$fp_dat){ PrintError("DATファイルが開けません"); }

	flock($fp_dat, LOCK_EX);
	while(!feof($fp_dat)){
		$dat[] = fgets($fp_dat);
		$dat_num++;
	}
	//MAX_RESマデ
	if ($dat_num > MAX_RES){
		CloseFile($fp_subject, $fp_dat);
		PrintError("このスレッドにはもう書き込めません");
	}
	//重複チェック
	list($d1, $d2, $d3, $d4) = explode("<>", $dat[$dat_num-2]);
	if($d4 == $_POST['MESSAGE']){ 
		CloseFile($fp_subject, $fp_dat);
		PrintError("二重書き込みです");
	}

	//DATに追加
	$name    = GenerateName($_POST['FROM']);
	$mail    = GenerateMail($_POST['mail']);
	$date    = GenerateDate();
	$message = GenerateMessage($_POST['MESSAGE']);
	$id      = GenerateID();
	
	list(,,,,$title) = explode("<>", $dat[0]);
	$title = rtrim($title);

	$dat_line = "$name<>$mail<>$date<> $id<> $message <>\n";
	$sub_line = "{$_POST['key']}.dat<>$title ($dat_num)\n";
	$match_count = substr_count("$dat_line$sub_line", "<>");
	if($match_count != 6){
		CloseFile($fp_subject, $fp_dat);
		PrintError("投稿できませんでした");
	}

	$dat[] = $dat_line;

	//DATファイルに書き込む
	ftruncate($fp_dat,0);
	rewind($fp_dat);
	fputs($fp_dat, implode("", $dat));
	CloseFile($fp_dat);

	//$subject編集
	array_splice($subject, $save_subject_num, 1);
	array_unshift($subject, $sub_line);

	//subject.txtに書き込む
	ftruncate($fp_subject,0);
	rewind($fp_subject);
	fputs($fp_subject, implode("", $subject));
	CloseFile($fp_subject);
}


//ファイル閉じる
function CloseFile($fp1, $fp2 = null){
	if($fp1){
		flock($fp1, LOCK_UN);
		fclose($fp1);
	}
	if($fp2){
		flock($fp2, LOCK_UN);
		fclose($fp2);
	}
}


//書き込み成功
function PrintOK(){
	$url = GenerateURL() . "test/read.cgi/{$_POST['bbs']}/{$_POST['key']}/";

	setcookie("NAME", $_POST["FROM"], $_SERVER['REQUEST_TIME']+60*60*24*180);
	setcookie("MAIL", $_POST["mail"], $_SERVER['REQUEST_TIME']+60*60*24*180);

	header("Cache-Control: no-cache");
	header("Content-type: text/html; charset=shift_jis");

	print "<html><!-- 2ch_X:true --><head><title>書きこみました。</title><meta http-equiv=\"refresh\" content=\"1;URL=$url\"></head>";
	print "<body>書きこみが終わりました。<br><br><a href=\"$url\">画面を切り替える</a>までしばらくお待ち下さい。</body></html>";

	exit;
}


//書き込み失敗
function PrintError($str){
	header("Cache-Control: no-cache");
	header("Content-type: text/html; charset=shift_jis");

	print "<html><!-- 2ch_X:error --><head><title>ＥＲＲＯＲ！</title>\n</head>";
	print "<body><b>ＥＲＲＯＲ：$str</b>\n";
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


//日付欄処理
function GenerateDate(){
	$week = array('日','月','火','水','木','金','土');

	$ymd = date("Y/m/d", $_SERVER['REQUEST_TIME']);
	$w   = $week[date("w", $_SERVER['REQUEST_TIME'])];
	$hms = date("H:i:s", $_SERVER['REQUEST_TIME']);

	return "$ymd($w) $hms";
}


//名前欄処理
function GenerateName($name){
	$name = str_replace(array("\r\n","\r","\n"), "", $name);
	$name = str_replace("&", "&amp;", $name);
	$name = str_replace("<", "&lt;", $name);
	$name = str_replace(">", "&gt;", $name);
	$name = str_replace("\"", "&quot;", $name);

	$name = str_replace("★", "☆", $name);
	$name = str_replace("◆", "◇", $name);
	$name = str_replace("●", "○", $name);

	if(!$name){ $name = NANASHI_SAN; }
	if (preg_match("/#(.+)$/", $name, $match)) {
		$tripkey = $match[1];
		$salt = substr($tripkey . 'H.', 1, 2);
		$salt = preg_replace('/[^\.-z]/', '.', $salt);
		$salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');

		$trip = crypt($tripkey, $salt);
		$trip = substr($trip, -10);
		$name = preg_replace("/#(.+)$/", "", $name);
		$name = $name . ' ◆' . $trip;
	}

	return $name;
}


//メール欄処理
function GenerateMail($mail){
	$mail = str_replace(array("\r\n","\r","\n"), "", $mail);
	$mail = str_replace("&", "&amp;", $mail);
	$mail = str_replace("<", "&lt;", $mail);
	$mail = str_replace(">", "&gt;", $mail);
	$mail = str_replace("\"", "&quot;", $mail);
	$mail = preg_replace('/#(.*)/', '', $mail);

	return $mail;
}


//題名欄処理
function GenerateSubject($subject){
	$subject = str_replace(array("\r\n","\r","\n"), "", $subject);
	$subject = str_replace("&", "&amp;", $subject);
	$subject = str_replace("<", "&lt;", $subject);
	$subject = str_replace(">", "&gt;", $subject);
	$subject = str_replace("\"", "&quot;", $subject);

	return $subject;
}


//本文欄処理
function GenerateMessage($message){
	$message = str_replace("<", "&lt;", $message);
	$message = str_replace(">", "&gt;", $message);
	$message = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $message);
	$message = str_replace(array("\r\n","\r","\n"), "<br>", $message);

	return $message;
}


//idを作成する
function GenerateID(){
	$str_md5 = substr(md5($_SERVER['REMOTE_ADDR']), 0, 30);
	$date_md5 = substr(md5(date("Y-m-d")), 0, 20);
	$key_md5 = substr(md5("sPeEtafUN7"), 0, 10);

	$id_md5 = md5($str_md5 . $date_md5 . $key_md5);
	$id = substr(base64_encode($id_md5), 0, 8);

	return $id;
}


function RecieveRawPost(){
	$posts = explode('&', file_get_contents('php://input'));
	foreach($posts as $buf){
		list($key, $value) = explode('=', $buf);
		$_POST["$key"] = urldecode($value);
	}
}
