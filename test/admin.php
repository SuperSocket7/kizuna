<?php


//���C������
if($_POST['mail'] != ADMIN_PASSWORD){ return; }

switch($_POST['FROM']){
	case "���A":
		Fukki(); break;
	case "�q��":
		DatOchi(); break;
	case "2ch":
		Import2ch(); break;
	case "�폜":
		if(preg_match("/^http/", $_POST['MESSAGE'])){ DeleteThread(); }
		else { DeleteRes(); } break;
	default:
		PrintError("�R�}���h���Ⴂ�܂�");
}
//���C������ ����� �ȉ��T�u���[�`��



function DeleteThread(){
	$command = str_replace("\r", "", $_POST['MESSAGE']);
	$urllist = explode("\n", $command);
	foreach ($urllist as $url){
		preg_match("/\/(\d+)\/$/", $url, $matched);
		$key = $matched[1];
		$filepath = "../{$_POST['bbs']}/dat/$key.dat";
		if(file_exists($filepath)){
			unlink($filepath);
			$dellist[] = $key;
		}
	}
	DeleteFromSubject($dellist);
	
	PrintError("�X���b�h���폜���܂���");
}


function DeleteRes(){
	if(!$_POST['key']){ PrintError("���X�̍폜�͊Y���X���b�h����s���K�v������܂�"); }

	if(preg_match("/^>>(\d+)\-?(\d+)?/", $_POST['MESSAGE'], $matched)){
		$start = $matched[1];
		$end   = $matched[2];
	}
	else{
		PrintError("���X�̍폜�́u>>5�v�̂悤�Ɏw�肵�Ă�������");
	}

	if($start == 1){ PrintError("1�Ԗڂ̃��X�͍폜�ł��܂���"); }
	if($start <  1){ PrintError("���̔ԍ��͍폜�ł��܂���"); }

	if($end > $start){
		for($i=$start; $i<=$end; $i++){
			$delres[] = $i;
		}
	}
	else{
		$delres[] = $start;
	}

	$fp_dat = fopen("../{$_POST['bbs']}/dat/{$_POST['key']}.dat", "rb+");
	if(!$fp_dat){ PrintError("DAT�t�@�C�����J���܂���"); }

	flock($fp_dat, LOCK_EX);
	while(!feof($fp_dat)){
		$j++;
		$pad = "";
		$line = fgets($fp_dat);
		foreach($delres as $no){
			if($no == $j){
				$padlength = strlen($line) - strlen("���ځ[��<>���ځ[��<>���ځ[��<>���ځ[��<>\n");
				if($padlength > 0){
					$pad  = str_repeat(" ", $padlength);
				}
				$line = "���ځ[��<>���ځ[��<>���ځ[��<>���ځ[��<>$pad\n";
				break;
			}
		}
		$dat[] = $line;
	}

	ftruncate($fp_dat,0);
	rewind($fp_dat);
	fputs($fp_dat, implode("", $dat));
	flock($fp_dat, LOCK_UN);
	fclose($fp_dat);
	
	PrintError("���X���폜���܂���");
}


function DatOchi(){
	if(!preg_match("/^http/", $_POST['MESSAGE'])){ PrintError("�X���b�h�𗎂Ƃ��ꍇ�́u�X����URL�v����͂��Ă�������"); }

	$command = str_replace("\r", "", $_POST['MESSAGE']);
	$urllist = explode("\n", $command);
	foreach ($urllist as $url){
		preg_match("/\/(\d+)\/$/", $url, $matched);
		$key = $matched[1];
		$filepath = "../{$_POST['bbs']}/dat/$key.dat";
		if(file_exists($filepath)){//DatOchi()��DeleteThread()�ƂقƂ�Ǔ��������A�������Ⴄ�B
			$key4 = substr($key,0,4);
			$key5 = substr($key,0,5);
			MakeDir("../{$_POST['bbs']}", "kako");
			MakeDir("../{$_POST['bbs']}/kako", $key4);
			MakeDir("../{$_POST['bbs']}/kako/$key4", $key5);
			rename($filepath, "../{$_POST['bbs']}/kako/$key4/$key5/$key.dat");
			$dellist[] = $key;
		}
	}
	DeleteFromSubject($dellist);

	PrintError("�X���b�h��DAT�������܂���");
}


function DeleteFromSubject($dellist){ //subject.txt����폜����L�[��z��Œ���
	$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
	flock($fp_subject, LOCK_EX);

	while(!feof($fp_subject)){
		$line = fgets($fp_subject);
		foreach($dellist as $key){
			if(preg_match("/^$key\.dat<>/", $line)){ continue 2; }
		}
		$newsubject[] = $line;
	}

	ftruncate($fp_subject,0);
	rewind($fp_subject);
	fputs($fp_subject, implode("", $newsubject));
	flock($fp_subject, LOCK_UN);
	fclose($fp_subject);
}


function Fukki(){
	$datdir  = "../{$_POST['bbs']}/dat";

	$dp = opendir($datdir);
	while (($filename = readdir($dp)) !== false) {
		if (preg_match("/^\d+\.dat$/", $filename)) {
			$mtime = filemtime("$datdir/$filename");
			$lastupdate[$mtime] = $filename;
		}
	} 
	closedir($dp);
	krsort($lastupdate);

	foreach ($lastupdate as $key => $filename){
		$dat = file("$datdir/$filename");
		$count = count($dat);
		list(,,,,$title) = explode("<>", $dat[0]);
		$title = rtrim($title);
		$subject[] = "$filename<>$title ($count)\n";
	}

	$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
	if(!$fp_subject){ PrintError("subject.txt���J���܂���"); }
	flock($fp_subject, LOCK_EX);
	ftruncate($fp_subject,0);
	rewind($fp_subject);
	fputs($fp_subject, implode("", $subject));
	flock($fp_subject, LOCK_UN);
	fclose($fp_subject);
	
	PrintError("{$_POST['bbs']}/subject.txt�̕��A����!");
}


function Import2ch(){
	$command = str_replace("\r", "", $_POST['MESSAGE']);
	$urllist = explode("\n", $command);

	foreach ($urllist as $url){
		$host = $bbs = $key = $i = $flag = "";
		$newdat     = array();
		$newsubject = array();
		
		list(,,$host,,,$bbs,$key,$option) = explode("/", $url);
		if(preg_match("/\.2ch\.net/", $host) and $bbs and $key){
			$daturl = "http://$host/$bbs/dat/$key.dat";
		}
		else{ continue; }

		$dat = @file($daturl);
		$delimcount = substr_count($dat[0], '<>');
		if($delimcount != 4){ continue; }

		foreach($dat as $line){
			$i++;
			list($name, $mail, $date, $text, $title) = explode("<>", $line);
			$name = strip_tags($name);
			$date = strip_tags($date);
			$text = strip_tags($text, '<br><br />');
			$newdat[] = "$name<>$mail<>$date<>$text<>$title";
			if($i == 1){
				if(!$title){ continue 2; }
				$save_title = rtrim($title);
				if($option == 1){ break; }
			}
		}
			
		$fp_subject = fopen("../{$_POST['bbs']}/subject.txt", "rb+");
		if(!$fp_subject){ PrintError("subject.txt���J���܂���"); }
		flock($fp_subject, LOCK_EX);
		while(!feof($fp_subject)){
			$line = fgets($fp_subject);
			if(preg_match("/^$key\.dat<>/", $line)){
				$line = "$key.dat<>$save_title ($i)\n";
				$flag = 1;
			}
			$newsubject[] = $line;
		}
		if(!$flag){ array_unshift($newsubject, "$key.dat<>$save_title ($i)\n"); }

		file_put_contents("../{$_POST['bbs']}/dat/$key.dat", implode("", $newdat));

		ftruncate($fp_subject,0);
		rewind($fp_subject);
		fputs($fp_subject, implode("", $newsubject));
		flock($fp_subject, LOCK_UN);
		fclose($fp_subject);

	}

	PrintError("2ch����X����A�����܂���");
}


function MakeDir($path, $name){//�f�B���N�g�������p�X�ƃf�B���N�g�����𒸑�
	if(!is_dir("$path/$name")){
		$permission = decoct(fileperms("../{$_POST['bbs']}/dat") & 0777);
		$flag = mkdir("$path/$name", octdec($permission));// umask�Ńp�[�~�b�V���������邩��
		chmod("$path/$name", octdec($permission));// �����Ō��ɖ߂�
		if(!$flag){ PrintError("�u$path�v�Ƀf�B���N�g�����쐬�ł��܂���"); }
	}
}
