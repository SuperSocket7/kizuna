<?php

error_reporting(0);
date_default_timezone_set('Asia/Tokyo');

include("./setting.php");
if(!$_POST['bbs']){ RecieveRawPost(); } //�N���C�A���g�ɂ��$_POST�Ƀf�[�^������Ȃ��o�O�΍�


//���C������
GoToAdmin();
CheckBBS();
CheckPost();
if($_POST['key'])
		{ WriteRes(); }
		else
		{ WriteThread(); }
PrintOK();
exit;
//���C������ ����� �ȉ��T�u���[�`��


//�Ǘ����[�h�ɕ��򂷂邩�ǂ���
function GoToAdmin(){
	if(!in_array($_POST['FROM'], array('�폜', '�q��', '���A', '2ch'))){ return; }
	if(!preg_match("/^#/", $_POST['mail'])){ return; }
	if(!$_POST['bbs']){ return; }

	include('./admin.php');
}


//�ƃX���b�h�̊m�F
function CheckBBS(){
	if(!$_POST['bbs']){ PrintError("�s���ȃL�[�ł�"); }
	if(preg_match("/[\.\/]/", $_POST['bbs'])){ PrintError("�s���ȃL�[�ł�"); }
	if(preg_match("/[\.\/]/", $_POST['key'])){ PrintError("�s���ȃL�[�ł�"); }


	if(!is_file("../{$_POST['bbs']}/subject.txt")){ PrintError("����ȔȂ��ł�"); }
	if($_POST['key'] and !is_file("../{$_POST['bbs']}/dat/{$_POST['key']}.dat")){
		$key4 = substr($_POST['key'], 0, 4);
		$key5 = substr($_POST['key'], 0, 5);
		if(is_file("../{$_POST['bbs']}/kako/$key4/$key5/{$_POST['key']}.dat")){ PrintError("���̃X���b�h�ɂ͂����������߂܂���"); }
		else { PrintError("����ȃX���b�h�Ȃ��ł�"); }
	}
}


//�K�������������B�쐬��
function CheckPost(){
	if($_POST['MESSAGE'] == '') { PrintError("�{������͂��Ă�������"); }
	if($_POST['FROM'] == " ") { PrintError("���O�����󔒂ɂ��邱�Ƃ͂ł��܂���"); }
	if($_POST['MESSAGE'] == " ") { PrintError("�{������͂��Ă�������"); }
	if($_POST['subject'] == '' and !$_POST['key']){ PrintError("�薼����͂��Ă�������"); }
	
	if(strlen($_POST['subject']) > MAX_SUBJECT){ PrintError("�薼�����傫�����܂�"); }
	if(strlen($_POST['FROM'])    > MAX_FROM)   { PrintError("���O�����傫�����܂�"); }
	if(strlen($_POST['mail'])    > MAX_MAIL)   { PrintError("URL�����傫�����܂�"); }
	if(strlen($_POST['MESSAGE']) > MAX_MESSAGE){ PrintError("�{�������傫�����܂�"); }

	//reCapcha����
	$recaptcha = htmlspecialchars($_POST["g-recaptcha-response"],ENT_QUOTES,'UTF-8');
	if($_POST['g-recaptcha-response'] == '') { PrintError("���e�ł��܂���"); }
	$resp = @file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={SECRETKEY}&response={$recaptcha}");
	$resp_result = json_decode($resp,true);

	if(intval($resp_result["success"]) !== 1) {
		return;
	} else {
		PrintError("���e�ł��܂���");
	}
}


//�X������
function WriteThread(){
	$_POST['key'] = $_SERVER['REQUEST_TIME'];

	//subject.txt�F$subjectlist�ɑS�f�[�^�i�[
	$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
	if(!$fp_subject){ PrintError("subject.txt���J���܂���"); }

	flock($fp_subject, LOCK_EX);
	while(!feof($fp_subject)){
		$subjectlist[] = fgets($fp_subject);
	}

	//DAT�t�@�C���ɏ�������
	$name    = GenerateName($_POST['FROM']);
	$mail    = GenerateMail($_POST['mail']);
	$date    = GenerateDate();
	$message = GenerateMessage($_POST['MESSAGE']);
	$subject = GenerateSubject($_POST['subject']);
	$id      = GenerateID();

	if(is_file("../{$_POST['bbs']}/dat/{$_POST['key']}.dat")){
		CloseFile($fp_subject);
		PrintError("������x���e���Ă�������");
	}

	$fp_dat = fopen("../{$_POST['bbs']}/dat/{$_POST['key']}.dat", "wb+");
	if(!$fp_dat){
		CloseFile($fp_subject);
		PrintError("DAT�t�@�C�����쐬�ł��܂���");
	}

	$dat_line = "$name<>$mail<>$date<> $id<> $message <>$subject\n";
	$sub_line = "{$_POST['key']}.dat<>$subject (1)\n";
	$match_count = substr_count("$dat_line$sub_line", "<>");
	if($match_count != 6){
		CloseFile($fp_subject, $fp_dat);
		PrintError("���e�ł��܂���ł���");
	}

	flock($fp_dat, LOCK_EX);
	fputs($fp_dat, $dat_line);
	CloseFile($fp_dat);

	//subject.txt�ɏ�������
	array_unshift($subjectlist, $sub_line);
	ftruncate($fp_subject,0);
	rewind($fp_subject);
	fputs($fp_subject, implode("", $subjectlist));
	CloseFile($fp_subject);
}


//���X��������
function WriteRes(){
	$subject_num = $dat_num = 0;
	//subject.txt�F�����������Ƃ���X����������{$subject�ɑS�f�[�^�i�[
	$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
	if(!$fp_subject){ PrintError("subject.txt���J���܂���"); }

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

	//DAT�t�@�C���F$dat�ɑS�f�[�^�i�[
	$fp_dat = fopen("../{$_POST['bbs']}/dat/{$_POST['key']}.dat", "rb+");
	if(!$fp_dat){ PrintError("DAT�t�@�C�����J���܂���"); }

	flock($fp_dat, LOCK_EX);
	while(!feof($fp_dat)){
		$dat[] = fgets($fp_dat);
		$dat_num++;
	}
	//MAX_RES�}�f
	if ($dat_num > MAX_RES){
		CloseFile($fp_subject, $fp_dat);
		PrintError("���̃X���b�h�ɂ͂����������߂܂���");
	}
	//�d���`�F�b�N
	list($d1, $d2, $d3, $d4) = explode("<>", $dat[$dat_num-2]);
	if($d4 == $_POST['MESSAGE']){ 
		CloseFile($fp_subject, $fp_dat);
		PrintError("��d�������݂ł�");
	}

	//DAT�ɒǉ�
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
		PrintError("���e�ł��܂���ł���");
	}

	$dat[] = $dat_line;

	//DAT�t�@�C���ɏ�������
	ftruncate($fp_dat,0);
	rewind($fp_dat);
	fputs($fp_dat, implode("", $dat));
	CloseFile($fp_dat);

	//$subject�ҏW
	array_splice($subject, $save_subject_num, 1);
	array_unshift($subject, $sub_line);

	//subject.txt�ɏ�������
	ftruncate($fp_subject,0);
	rewind($fp_subject);
	fputs($fp_subject, implode("", $subject));
	CloseFile($fp_subject);
}


//�t�@�C������
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


//�������ݐ���
function PrintOK(){
	$url = GenerateURL() . "test/read.cgi/{$_POST['bbs']}/{$_POST['key']}/";

	setcookie("NAME", $_POST["FROM"], $_SERVER['REQUEST_TIME']+60*60*24*180);
	setcookie("MAIL", $_POST["mail"], $_SERVER['REQUEST_TIME']+60*60*24*180);

	header("Cache-Control: no-cache");
	header("Content-type: text/html; charset=shift_jis");

	print "<html><!-- 2ch_X:true --><head><title>�������݂܂����B</title><meta http-equiv=\"refresh\" content=\"1;URL=$url\"></head>";
	print "<body>�������݂��I���܂����B<br><br><a href=\"$url\">��ʂ�؂�ւ���</a>�܂ł��΂炭���҂��������B</body></html>";

	exit;
}


//�������ݎ��s
function PrintError($str){
	header("Cache-Control: no-cache");
	header("Content-type: text/html; charset=shift_jis");

	print "<html><!-- 2ch_X:error --><head><title>�d�q�q�n�q�I</title>\n</head>";
	print "<body><b>�d�q�q�n�q�F$str</b>\n";
	print "<br><a href=\"javascript:history.back()\">�߂�</a></body></html>";

	exit;
}


function GenerateURL(){
	if($_SERVER['HTTPS']=="on"){ $protocol = "https://"; }
	else{ $protocol = "http://"; }

	$request_uri = preg_replace("/\/test\/.*/", "/", $_SERVER['REQUEST_URI']); // /test/�ȉ����폜

	$url = $protocol . $_SERVER["HTTP_HOST"] . $request_uri;
	return $url;
}


//���t������
function GenerateDate(){
	$week = array('��','��','��','��','��','��','�y');

	$ymd = date("Y/m/d", $_SERVER['REQUEST_TIME']);
	$w   = $week[date("w", $_SERVER['REQUEST_TIME'])];
	$hms = date("H:i:s", $_SERVER['REQUEST_TIME']);

	return "$ymd($w) $hms";
}


//���O������
function GenerateName($name){
	$name = str_replace(array("\r\n","\r","\n"), "", $name);
	$name = str_replace("&", "&amp;", $name);
	$name = str_replace("<", "&lt;", $name);
	$name = str_replace(">", "&gt;", $name);
	$name = str_replace("\"", "&quot;", $name);

	$name = str_replace("��", "��", $name);
	$name = str_replace("��", "��", $name);
	$name = str_replace("��", "��", $name);

	if(!$name){ $name = NANASHI_SAN; }
	if (preg_match("/#(.+)$/", $name, $match)) {
		$tripkey = $match[1];
		$salt = substr($tripkey . 'H.', 1, 2);
		$salt = preg_replace('/[^\.-z]/', '.', $salt);
		$salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');

		$trip = crypt($tripkey, $salt);
		$trip = substr($trip, -10);
		$name = preg_replace("/#(.+)$/", "", $name);
		$name = $name . ' ��' . $trip;
	}

	return $name;
}


//���[��������
function GenerateMail($mail){
	$mail = str_replace(array("\r\n","\r","\n"), "", $mail);
	$mail = str_replace("&", "&amp;", $mail);
	$mail = str_replace("<", "&lt;", $mail);
	$mail = str_replace(">", "&gt;", $mail);
	$mail = str_replace("\"", "&quot;", $mail);
	$mail = preg_replace('/#(.*)/', '', $mail);

	return $mail;
}


//�薼������
function GenerateSubject($subject){
	$subject = str_replace(array("\r\n","\r","\n"), "", $subject);
	$subject = str_replace("&", "&amp;", $subject);
	$subject = str_replace("<", "&lt;", $subject);
	$subject = str_replace(">", "&gt;", $subject);
	$subject = str_replace("\"", "&quot;", $subject);

	return $subject;
}


//�{��������
function GenerateMessage($message){
	$message = str_replace("<", "&lt;", $message);
	$message = str_replace(">", "&gt;", $message);
	$message = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $message);
	$message = str_replace(array("\r\n","\r","\n"), "<br>", $message);

	return $message;
}


//id���쐬����
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
