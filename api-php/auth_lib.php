<?php
function make_token(): string {
  return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function hash_token(string $token): string {
  return hash('sha256', $token);
}