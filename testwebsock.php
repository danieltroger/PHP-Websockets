#!/usr/bin/env php
<?php
function shutdown()
{
  echo "Shutting down...\r";
  fclose($GLOBALS['l']);
  exit("Bye             " . PHP_EOL);
}

if(function_exists("pcntl_fork"))
{
  declare(ticks = 1);
  define("daemon",true);
  define("logfile","server.log");
  echo "Forking to background, ";
  $fork = pcntl_fork();
  if ($fork == -1)
  {
     die('Could not fork');
  }
  else if ($fork)
  {
     echo "Parent PID was " . posix_getpid() . ", dying." . PHP_EOL;
     exit;
  }
  else
  {
    define("PID",posix_getpid());
    pcntl_signal(SIGTERM, 'shutdown');
    pcntl_signal(SIGHUP,  'shutdown');
    pcntl_signal(SIGABRT, 'shutdown');
    pcntl_signal(SIGQUIT, 'shutdown');
    pcntl_signal(SIGILL, 'shutdown');
  }
}
else
{
  define("daemon",false);
}
if(daemon)
{
  echo "We are chind and have PID " . PID . PHP_EOL;
  $l = fopen(logfile,"w");
}

require_once('./websockets.php');

class echoServer extends WebSocketServer {
  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.
  public function quit()
  {
    foreach($this->users as $u)
    {
      $this->send($u,"MSG:SERVER:Disconnecting due to internal shutdown.");
      $this->disconnect($u->socket);
    }
  }
  protected function process ($user, $message)
  {
    $msg = explode(":",$message);
    if($message == "NAMES")
    {
      $nicks = Array();
      foreach($this->users as $u){if(!$u->hasSentClose){$nicks[] = $u->nick;}}
      $this->send($user,"MSG:SERVER: Currently connected users are: " . implode(", ",$nicks));
    }
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
            if(!$u->hasSentClose)
            {
              if($text == $u->nick)
              {
                $this->send($user,"BAD_UNAME:taken");
                return;
              }
            }
          }
          if($user->nick == NULL)
          {
            $this->stdout("{$text} connected.");
            $user->nick = $text;
            $nicks = Array();
            foreach($this->users as $u)
            {
              if(!$u->hasSentClose)
              {
                $this->send($u,"JOIN:{$text}");
                $nicks[] = $u->nick;
              }
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
