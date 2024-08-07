<?php

error_reporting(0);
session_start();

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Rakit\Validation\Validator;
use Dotenv\Dotenv;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

if (empty($_ENV['DISCORD_HOOK'])) {
  error_log('DISCORD_HOOK epmty!');
  exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$token || $token !== $_SESSION['__token']) {
  http_response_code(405);
  exit;
}

$json = json_decode(file_get_contents('php://input'), true) ?? [];

$validator = new Validator;

$validation = $validator->make($_REQUEST + $json, [
  'name' => 'required|max:100',
  'email' => 'required|email|max:100',
  'message' => 'required|max:1000',
]);

$validation->validate();

if ($validation->fails()) {
  echo json_encode(['errors' => $validation->errors()->all()], true);
  exit;
}

$validatedData = $validation->getValidatedData();
$validatedData['ip'] = getClientIP();

$message = "IP: " . $validatedData['ip'] . "\nName: " . $validatedData['name'] . "\nEmail: " . $validatedData['email'] . "\nMessage: " . $validatedData['message'];

$response = httpPost($_ENV['DISCORD_HOOK'], [
  'content' => $message
]);

echo json_encode(['success' => 'Your message has been sent!'], true);

function getClientIP()
{
  if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) {
    return $_SERVER['HTTP_CF_CONNECTING_IP'];
  } else if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
    return $_SERVER['REMOTE_ADDR'];
  } else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
    return $_SERVER['HTTP_CLIENT_IP'];
  }
  return '';
}

function httpPost($url, $data)
{
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);
  return $response;
}
