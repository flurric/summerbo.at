<?php
function buildDatabaseConnection($config) {
  //Connect to DB only here to save response time on other commands
  try {
    $dbConnection = new PDO('mysql:dbname=' . $config['dbname'] . ';host=' . $config['dbserver'] . ';port=' . $config['dbport'] . ';charset=utf8mb4', $config['dbuser'], $config['dbpassword'], array(PDO::ATTR_TIMEOUT => 25));
    $dbConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $e) {
    notifyOnException('Database Connection', $config, '', $e);
  }
  return $dbConnection;
}

function notifyOnException($subject, $config, $sql = '', $e = '', $fail = true) {
  $to = $config['mail'];
  $txt = __FILE__ . ' ' . $sql . ' Error: ' . $e;
  $headers = 'From: ' . $config['mail'];
  mail($to, $subject, $txt, $headers);
  http_response_code(200);
  if($fail) {
    die();
  }
}

function checkRegValid($userId){
  global $dbConnection, $config;
  try {
    $sql = "SELECT id FROM users WHERE id = $userId";
    $stmt = $dbConnection->prepare('SELECT id FROM users WHERE id = :userId');
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $row = $stmt->fetch();
  }catch (PDOException $e){
    notifyOnException('Database Select', $config, $sql, $e);}
  if ($row->numRows() === 1) {
    //Already logged in, redirect to userarea
    return true;
  }
  else {
    //Clear Session and Cookies
    return false;
  }
}

function getRegDetails($userId, $columns = '*'){
  global $dbConnection, $config;

  try {
    $sql = "SELECT $columns FROM users WHERE id = '$userId'";
    $stmt = $dbConnection->prepare("SELECT $columns FROM users WHERE id = :userId");
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $row = $stmt->fetch();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  if (!empty($row)) {
    return $row;
  }
  else {
    return 'Not found';
  }
}
function getPaymentDetails($userId, $columns = '*'){
  global $dbConnection, $config;

  try {
    $sql = "SELECT $columns FROM payments INNER JOIN users ON users.id = payments.user_id WHERE id = '$userId'";
    $stmt = $dbConnection->prepare("SELECT $columns FROM payments INNER JOIN users ON users.id = payments.user_id WHERE id = :userId");
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $row = $stmt->fetch();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  if (!empty($row)) {
    return $row;
  }
  else {
    return 'Not found';
  }
}

function getUserRank($userId) {
  global $dbConnection, $config;

  try {
    $sql = "SELECT `rank` FROM users WHERE id = '$userId'";
    $stmt = $dbConnection->prepare("SELECT `rank` FROM users WHERE id = :userId");
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $row = $stmt->fetch();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  if (!empty($row)) {
    return $row['rank'];
  }
  else {
    return 0;
  }
}

//dbConnection needs to be set
function requestRegistrationConfirm($userId, $email) {
  global $dbConnection, $config;
  if (!isset($dbConnection)) {
    mail($config['mail'], 'CODE BUG, NOT SET VAR', 'SET VAR BEFORE USAGE OF requestRegistrationConfirm');
    die();
  }
  try {
    $sql = "SELECT nickname FROM users WHERE id = $userId";
    $stmt = $dbConnection->prepare('SELECT nickname FROM users WHERE id = :userId');
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $row = $stmt->fetch();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  if($stmt->rowCount() > 0) {
    $nickname = $row['nickname'];
    $token = getRandomString(40);
    try {
      $sql = "INSERT INTO email_tokens(id, token) VALUES ($userId, $token)";
      $stmt = $dbConnection->prepare('INSERT INTO email_tokens(id, token) VALUES (:userId, :token)');
      $stmt->bindParam(':userId', $userId);
      $stmt->bindParam(':token', $token);
      $stmt->execute();
    } catch (PDOException $e) {
      notifyOnException('Database Insert', $config, $sql, $e);
    }

    $confirmationLink = 'https://' . $config['sitedomain'] . '/confirm?token=' . $token;

    sendEmail($email, 'Summerbo.at Confirmation', "Dear $nickname,

Thank you for your registration with the Summernights party.

Your current status is: NEW - Regnumber $userId

You first have to verify your email address and confirm your registration by clicking on the following link: <a href=\"$confirmationLink\">$confirmationLink</a>
Afterwards another mail will be sent.

If you have any questions, please send us a message. Reply to this e-mail or contact us via Telegram at <a href=\"https://t.me/summerboat\">https://t.me/summerboat</a>.

Your Boat Party Crew
");
  }
}

function confirmRegistration($token) {
  global $dbConnection, $config;
  try {
    $sql = "UPDATE users INNER JOIN email_tokens on users.id = email_tokens.id SET status = 1 WHERE token = '$token'";
    $stmt = $dbConnection->prepare('UPDATE users INNER JOIN email_tokens on users.id = email_tokens.id SET status = 1 WHERE token = :token');
    $stmt->bindParam(':token', $token);
    $stmt->execute();
  } catch (PDOException $e) {
    notifyOnException('Database Update', $config, $sql, $e);
    return false;
  }
  if ($stmt->rowCount() !== 1) {
    $data = array(
      'ip'      => $_SERVER["HTTP_CF_CONNECTING_IP"],
      'token'   => $token,
      'server'  => $_SERVER,
      'headers' => $http_response_header
    );
    mail($config['mail'], 'Potentially Malicious Reg-Confirm Attempt', print_r($data, true));
    return false;
  }
  else {
    try {
      $sql = "SELECT email, nickname, users.id FROM users INNER JOIN email_tokens on users.id = email_tokens.id WHERE token = '$token'";
      $stmt = $dbConnection->prepare('SELECT email, nickname, users.id FROM users INNER JOIN email_tokens on users.id = email_tokens.id WHERE token = :token');
      $stmt->bindParam(':token', $token);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($stmt->rowCount() === 1) {
        $email = $row['email'];
        $nickname = $row['nickname'];
        $userId = $row['id'];
        sendEmail($email, 'Summerbo.at - Email Confirmed', "Dear $nickname, 

You have successfully verified your email. 

Our registration team will now check your details. You will get another email about this soon.
It can take a couple of hours before your registration is accepted. You should receive another mail from us about the next step after being accepted.
It shouldn't take more than 24 hours.

Your current status is: VERIFIED - Regnumber $userId

If you have any questions, please send us a message. Reply to this e-mail or contact us via Telegram at https://t.me/summerboat.

Your Boat Party Crew
");
        sendStaffNotification($userId);
        /*

Your registration will be reviewed by our registration team after you have verified yourself.
It can take a couple of hours before your registration is accepted. You should receive another mail from us about the next step after being accepted.
It shouldn't take more than 24 hours.*/
      }
    } catch (PDOException $e) {
      notifyOnException('Database Select', $config, $sql, $e);
      return false;
    }
  }
  return true;
}

function approveRegistration($userId) {
  global $dbConnection, $config;

  try {
    $sql = "UPDATE users SET status = 2, approvedate = UNIX_TIMESTAMP() WHERE id = '$userId'";
    $stmt = $dbConnection->prepare('UPDATE users SET status = 2, approvedate = UNIX_TIMESTAMP() WHERE id = :userId');
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
  } catch (PDOException $e) {
    notifyOnException('Database Update', $config, $sql, $e);
    return false;
  }
  if ($stmt->rowCount() === 1) {
    return true;
  }
  else {
    return false;
  }
}
function rejectRegistration($userId){
  global $dbConnection, $config;

  try {
    $sql = "DELETE FROM users WHERE id = '$userId'";
    $stmt = $dbConnection->prepare('DELETE FROM users WHERE id = :userId');
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
  } catch (PDOException $e) {
    notifyOnException('Database Update', $config, $sql, $e);
    return false;
  }
  if ($stmt->rowCount() === 1) {
    list($email, $nickname) = getRegDetails($userId, 'email, nickname');
    sendEmail($email, 'Summerbo.at - Registration Canceled', "Dear $nickname,

Sadly we have to inform you that your registration has been canceled.

If you believe this was a mistake, please send us an email. It can be that there is still space. We will inform you with more information after checking the system. 

If you have any questions, please send us a message. Reply to this e-mail or contact us via Telegram at <a href=\"https://t.me/summerboat\">https://t.me/summerboat</a>.

Your Boat Party Crew");
    return true;
  }
  else {
    return false;
  }
}

function saveApplication($chatId, $name, $message) {
  global $dbConnection, $config;

  try {
    $sql = "INSERT INTO applications(chatId, name, message) VALUES ('$chatId', '$name', '$message')";
    $stmt = $dbConnection->prepare('INSERT INTO applications(chatId, name, message) VALUES (:chatId, :name, :message)');
    $stmt->bindParam(':chatId', $chatId);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':message', $message);
    $stmt->execute();
  } catch (PDOException $e) {
    notifyOnException('Database Insert', $config, $sql, $e, false);
    return false;
  }
  return true;
}

function sendStaffNotification($userId, $text = '') {
  global $config;

  foreach ($config['telegramAdmins'] as $admin) {
    if (empty($text)) {
      requestApproveMessage($admin, $userId);
    }
    else {
      sendMessage($admin, $text);
    }
  }
}

function buildApproveMarkup($userId){
  return array(
    'inline_keyboard' => array(
      array(
        array(
          'text' => 'View',
          'url'  => 'https://summerbo.at/admin/view.html?type=reg&id=' . $userId
        )
      ),
      array(
        array(
          'text'          => 'Approve',
          'callback_data' => $userId . '|approve|0'
        ),
        array(
          'text'          => 'Reject',
          'callback_data' => $userId . '|reject|0'
        )
      )
    )
  );
}

function requestApproveMessage($chatId, $userId){
  $replyMarkup = buildApproveMarkup($userId);
  sendMessage($chatId, "<b>New Registration on summerbo.at!</b>
Regnumber: $userId", json_encode($replyMarkup));
}

function approvePayment($userId, $approver, $amount) {
  global $dbConnection, $config;

  try {
    $sql = "SELECT topay, (SELECT COUNT(1) FROM payments WHERE user_id = '$userId') as paid FROM users WHERE id = '$userId'";
    $stmt = $dbConnection->prepare("SELECT topay, (SELECT COUNT(1) FROM payments WHERE user_id = :userId) as paid FROM users WHERE id = :userIdB");
    $stmt->bindParam(':userId', $userId);
    $stmt->bindParam(':userIdB', $userId);
    $stmt->execute();
    $row = $stmt->fetch();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  if ($stmt->rowCount() === 1) {
    if ($row['topay'] <= $amount) {
      $status = 3;
    }
    else {
      $status = 2;
    }
    if ($row['paid'] == 1) {
      try {
        $sql = "UPDATE payments INNER JOIN users on payments.user_id = users.id SET status = '$status', amount = $amount WHERE user_id = '$userId'";
        $stmt = $dbConnection->prepare("UPDATE payments INNER JOIN users on payments.user_id = users.id SET status = :status, amount = :amount WHERE user_id = :userId");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
      } catch (PDOException $e) {
        notifyOnException('Database Select', $config, $sql, $e);
      }
    }
    else {
      try {
        $sql = "INSERT INTO payments(user_id, date, approver_id, amount) VALUES ($userId, UNIX_TIMESTAMP(), $approver, $amount)";
        $stmt = $dbConnection->prepare("INSERT INTO payments(user_id, date, approver_id, amount) VALUES (:userId, UNIX_TIMESTAMP(), :approver, :amount)");
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':approver', $approver);
        $stmt->bindParam(':amount', $amount);
        $stmt->execute();
      } catch (PDOException $e) {
        notifyOnException('Database Select', $config, $sql, $e);
      }
    }
    if ($stmt->rowCount() > 0) {
      return true;
    }
  }
  return false;
}

function requestUnapproved($chatId){
  global $dbConnection, $config;
  try {
    $sql = "SELECT id FROM users WHERE status = 1";
    $stmt = $dbConnection->prepare("SELECT id FROM users WHERE status = 1");
    $stmt->execute();
    $rows = $stmt->fetchAll();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  foreach ($rows as $row) {
    requestApproveMessage($chatId, $row['id']);
    sleep(1);
  }
  sendMessage($chatId, 'Done.');
}

function sendEmail($address, $subject, $text) {


  /* ToDo: Just mail(); or PHPMailer TBD */

  /*$mail = new PHPMailer(); // create a new object
  $mail->IsSMTP(); // enable SMTP
  $mail->SMTPDebug = false; // debugging: 1 = errors and messages, 2 = messages only
  $mail->SMTPAuth = true; // authentication enabled
  $mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED
  $mail->Host = 'smtp.gmail.com';
  $mail->Port = 465; // or 587
  $mail->Username = 'fajournalmon@gmail.com';
  $mail->Password = 'KOHTcKg0X!Yu';
  $mail->SetFrom('fajournalmon@gmail.com');
  $mail->Subject = $subject;
  $mail->Body = $text;
  $mail->AddAddress($address);
  $mail->IsHTML(true);
  if ($address == 'admin@kieran.de') {
    $return = $mail->Send();
  } else {
    $return = 1;
  }
  if ($return != 1) {
    $to = 'admin@kieran.de';
    $subject = 'Error journal send mail';
    $txt = __FILE__ . ' Error: ' . $return . '<br>';
    $headers = 'From: fajournal@kieran.de';
    mail($to, $subject, $txt, $headers);
    die("Unable to send confirmation mail. You will receive an email later!");
  }*/
}

function getRandomString($Length) {
  $Chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  return substr(str_shuffle($Chars), 0, $Length);
}

function sendMessage($chatId, $text, $replyMarkup = '') {
  global $config;
  $response = file_get_contents($config['url'] . "sendMessage?disable_web_page_preview=true&parse_mode=html&chat_id=$chatId&text=" . urlencode($text) . "&reply_markup=$replyMarkup");
  //Might use http_build_query in the future
  return json_decode($response, true)['result'];
}