<?php
// Copie este arquivo para config.php somente no servidor.

const DB_HOST = 'localhost';
const DB_NAME = 'NOME_DO_BANCO_PRINCIPAL';
const DB_USER = 'USUARIO_DO_BANCO_PRINCIPAL';
const DB_PASS = 'COLOQUE_A_SENHA_DO_BANCO_PRINCIPAL_AQUI';

const API_KEY = 'COLOQUE_A_CHAVE_FORTE_DA_API_AQUI';

const SESSION_NAME = 'LEME_API_SESSAO';
const CSRF_SESSION_KEY = 'csrf_token';
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;
const SESSION_IDLE_LIMIT_SECONDS = 1800;
const SESSION_REGENERATE_SECONDS = 600;

const ALLOWED_ORIGINS = [
    'https://lemesolucoesemti.com.br',
    'https://www.lemesolucoesemti.com.br'
];
