<?php


error_reporting(0);
date_default_timezone_set('Asia/Tokyo');

unset($_ENV);
$_ENV['bbs'] = basename(getcwd());
chdir(dirname(__FILE__));

include("./setting.php");

foreach(file("../{$_ENV['bbs']}/subject.txt") as $line){
	$i++;
	list($key, $subject) = explode("<>", $line);
	$subject = rtrim($subject);
	preg_match("/\((\d+)\)$/", $subject, $matches);
	$rescount = $matches[1];
	$subject = preg_replace("/\(\d+\)$/", "", $subject);
	$key = str_replace(".dat", "", $key);

	$html = BBS_HTML;
	$html = str_replace('[[no]]', $i, $html);
	$html = str_replace('[[bbs]]', $_ENV['bbs'], $html);
	$html = str_replace('[[key]]', $key, $html);
	$html = str_replace('[[subject]]', $subject, $html);
	$html = str_replace('[[rescount]]', $rescount, $html);

	$_ENV['threadlist'] .= "$html\n";
}

$_ENV['cookie_name'] = htmlspecialchars($_COOKIE["NAME"]);
$_ENV['cookie_mail'] = htmlspecialchars($_COOKIE["MAIL"]);
$_ENV['sitekey'] = SITEKEY;

header("Content-type: text/html; charset=shift_jis");
PrintTemplate("../{$_ENV['bbs']}/bbs.html");



function PrintTemplate($file){
	print preg_replace_callback('/(?:<\!--)?\[\[([^\]]+)\]\](?:-->)?/', 'CB', file_get_contents($file));
}
function CB($matches){
	return $_ENV["$matches[1]"];
}