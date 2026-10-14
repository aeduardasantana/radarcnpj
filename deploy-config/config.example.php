<?php
// Copie para config.php APENAS no servidor, fora do GitHub e fora de public_html.
// NUNCA publique senhas ou tokens no repositório.
return [
  'dsn' => 'mysql:host=SEU_HOST;port=3306;dbname=SEU_BANCO;charset=utf8mb4',
  'db_user' => 'SEU_USUARIO',
  'db_password' => 'SUA_SENHA',
  'api_key' => 'GERE_UM_TOKEN_ALEATORIO_FORTE',
  'max_export_rows' => 100000,
];
