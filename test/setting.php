<?php
//管理者のパスワード。パスワードの先頭は#を推奨
define('ADMIN_PASSWORD', '#kizuna');


//名無しさん
define('NANASHI_SAN', '名無しさん');

//1スレッド当たりの最大レス数
define('MAX_RES', 1000);

//題名の最大バイト数
define('MAX_SUBJECT', 256);

//名前の最大バイト数
define('MAX_FROM', 128);

//メールの最大バイト数
define('MAX_MAIL', 256);

//本文の最大バイト数
define('MAX_MESSAGE', 10240);

//bbs.html用  1スレごとのHTML
define('BBS_HTML', '<tr><td>[[no]]</td><td><a href="../test/read.cgi/[[bbs]]/[[key]]/">[[subject]]</a></td><td>[[rescount]]</td></tr>');

//thread.html用 1レスごとのHTML
define('THREAD_HTML', '<dt id="res[[no]]">[[no]] 名前：<span class="name">[[FROM_String]]</span> 投稿日：<span class="date">[[date]]</span> ID:<span class="id">[[id]]</span> <span class="url">[[mail]]</span></dt><dd id="mes[[no]]">[[MESSAGE]]</dd>');

//reCapchaのサイトキー
define('SITEKEY', '');

//reCapchaのシークレットキー
define('SECRETKEY', '');