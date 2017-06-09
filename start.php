#!/usr/bin/env php
<?php
if(isset($argv[1]) and $argv[1] == "background") {
  shell_exec("screen php start.php startb");
  system("clear");
  echo "\n TGUserbot avviato in background usando screen. \n";
  exit;
}
if(isset($argv[1]) and $argv[1] == "startb") {
  shell_exec("screen -d");
}
require 'vendor/autoload.php';
if (file_exists('.env')) {
    echo 'Caricando impostazioni.env...'.PHP_EOL;
    $dotenv = new Dotenv\Dotenv(getcwd());
    $dotenv->load();
}
echo 'Caricando impostazioni...'.PHP_EOL;
$settings = json_decode(getenv('MTPROTO_SETTINGS'), true) ?: [];
$MadelineProto = new \danog\MadelineProto\API($settings);
$MadelineProto = false;
try {
    $MadelineProto = \danog\MadelineProto\Serialization::deserialize('bot.madeline');
} catch (\danog\MadelineProto\Exception $e) {
}
if ($MadelineProto === false) {
    echo 'Loading MadelineProto...'.PHP_EOL;
    $MadelineProto = new \danog\MadelineProto\API($settings);
    if (getenv('TRAVIS_COMMIT') == '') {
        $checkedPhone = $MadelineProto->auth->checkPhone(// auth.checkPhone becomes auth->checkPhone
            [
                'phone_number'     => getenv('MTPROTO_NUMBER'),
           ]
        );
        \danog\MadelineProto\Logger::log([$checkedPhone], \danog\MadelineProto\Logger::NOTICE);
        $sentCode = $MadelineProto->phone_login(getenv('MTPROTO_NUMBER'));
        \danog\MadelineProto\Logger::log([$sentCode], \danog\MadelineProto\Logger::NOTICE);
        system("clear");
        echo 'Inserisci il codice ricevuto: ';
        $code = fgets(STDIN, (isset($sentCode['type']['length']) ? $sentCode['type']['length'] : 5) + 1);
        $authorization = $MadelineProto->complete_phone_login($code);
        \danog\MadelineProto\Logger::log([$authorization], \danog\MadelineProto\Logger::NOTICE);
        if ($authorization['_'] === 'account.noPassword') {
            throw new \danog\MadelineProto\Exception('2FA is enabled but no password is set!');
        }
        if ($authorization['_'] === 'account.password') {
            \danog\MadelineProto\Logger::log(['2FA is enabled'], \danog\MadelineProto\Logger::NOTICE);
            $authorization = $MadelineProto->complete_2fa_login(readline('Inserisci la password della verifica in due passaggi (hint '.$authorization['hint'].'): '));
        }
        if ($authorization['_'] === 'account.needSignup') {
            \danog\MadelineProto\Logger::log(['Registering new user'], \danog\MadelineProto\Logger::NOTICE);
            system("clear");
            $authorization = $MadelineProto->complete_signup(readline('Inserisci il nome: '), readline('Inserisci il cognome (per saltare premi invio): '));
        }
        echo 'Serializing MadelineProto to session.madeline...'.PHP_EOL;
        echo 'Wrote '.\danog\MadelineProto\Serialization::serialize('session.madeline', $MadelineProto).' bytes'.PHP_EOL;
        system("clear");
    } else {
        $MadelineProto->bot_login(getenv('BOT_TOKEN'));
    }
}
function sm($chatID, $text, $reply = 0) {
  global $update;
  global $MadelineProto;
  if(isset($chatID) and isset($text) and $reply == 0) var_export($MadelineProto->messages->sendMessage(['peer' => $chatID, 'message' => $text, 'parse_mode' => "HTML"]));
  if(isset($chatID) and isset($text) and $reply == 1) var_export($MadelineProto->messages->sendMessage(['peer' => $chatID, 'message' => $text, 'reply_to_msg_id' => $update['update']['message']['id'], 'parse_mode' => "HTML"]));
}
function leggimsg($chatID, $msgid) {
  global $update;
  global $MadelineProto;
  if(isset($chatID) and isset($msgid)) var_export($MadelineProto->messages->readHistory(['peer' => $chatID, 'max_id' => $msgid]));
}
function scrivendo($chatID) {
  global $update;
  global $MadelineProto;
  if(isset($chatID)) {
    $sendMessageTypingAction = ['_' => 'sendMessageTypingAction', ];
    var_export($MadelineProto->messages->setTyping(['peer' => $chatID, 'action' => $sendMessageTypingAction]));
  }
}
$offset = 0;
while (true) {
    $updates = $MadelineProto->API->get_updates(['offset' => $offset, 'limit' => 50, 'timeout' => 0]);
    foreach ($updates as $update) {
        $offset = $update['update_id'] + 1;
        if(isset($update['update']['message']['message'])) $msg = $update["update"]["message"]["message"];
        if(isset($update['update']['message']['to_id']['channel_id'])) {
          $chatID = $update['update']['message']['to_id']['channel_id'];
          $chatID = "channel#$chatID";
          $type = "supergruppo";
        }
        if(isset($update['update']['message']['to_id']['chat_id'])) {
          $chatID = $update['update']['message']['to_id']['chat_id'];
          $chatID = "chat#$chatID";
          $type = "gruppo";
        }
        if(isset($update['update']['message']['from_id'])) $userID = $update['update']['message']['from_id'];
        if(isset($update['update']['message']['to_id']['user_id'])) {
          $chatID = $update['update']['message']['from_id'];
          $type = "privata";
        }
        if(isset($update['update']['message']['id'])) $msgid = $update['update']['message']['id'];
        if(isset($msg)) {
          echo "Messaggio: $msg, uid: $userID, chatid: $chatID, tipo: $type \n";
          @leggimsg($chatID, $msgid);
          @include("bot.php");
        }
        //Pulizia
        unset($msg);
        unset($chatID);
        unset($userID);
        unset($type);
        unset($msgid);
    }
    $serialize = 'Wrote '.\danog\MadelineProto\Serialization::serialize('bot.madeline', $MadelineProto).' bytes'.PHP_EOL;
}