#!/usr/bin/env php
<?php

require_once('./websockets.php');

class echoServer extends WebSocketServer {
  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

  protected function process ($user, $message)
  {
    $msg = explode(":",$message);
    if(sizeof($msg) < 2)
    {
      $this->send($user,"BAD_MSG:too_short");
    }
    else
    {
      $cmd = $msg[0];
      unset($msg[0]);
      $args = &$msg;
      $text = implode(":",$msg);
      if($cmd == "U")
      {
        if(strpos($text,":") !== false)
        {
          $this->send($user,"BAD_UNAME:invalid");
        }
        else
        {
          foreach($this->users as $u)
          {
            if($text == $u->nick)
            {
              $this->send($user,"BAD_UNAME:taken");
              return;
            }
          }
          if($user->nick == NULL)
          {
            echo "{$text} connected." . PHP_EOL;
            $user->nick = $text;
            $nicks = Array();
            foreach($this->users as $u)
            {
              $this->send($u,"JOIN:{$text}");
              $nicks[] = $u->nick;
            }
            $this->send($user,"MSG:SERVER:Currently connected users are: " . implode(", ",$nicks));
          }
          else
          {
            foreach($this->users as $u)
            {
              $this->send($u,"RENAME:{$user->nick}:{$text}");
            }
            $user->nick = $text;
          }
        }
      }
      elseif($cmd == "MSG")
      {
        foreach($this->users as $us)
        {
          $this->send($us,"MSG:{$user->nick}:" . $text);
        }
      }
    }
  }

  protected function connected ($user) {
    $user->nick = NULL;
  }

  protected function closed ($user) {
    foreach($this->users as $u)
    {
      $this->send($u,"QUIT:{$user->nick}");
    }
  }
}

$echo = new echoServer("0.0.0.0","9000");

try {
  $echo->run();
}
catch (Exception $e) {
  $echo->stdout($e->getMessage());
}
