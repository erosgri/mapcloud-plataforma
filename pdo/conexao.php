<?php

function novaConexao($banco = 'mapcloud')
{
	$servidor = '127.0.0.1';
	$usuario = 'root';
	$senha = 'carabina22';

	try {
		$dsn = "mysql:host=$servidor;dbname=$banco;charset=utf8mb4";
		$opcoes = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
		];
		$conexao = new PDO($dsn, $usuario, $senha, $opcoes);
		return $conexao;
	} catch (PDOException $e) {
		die('Erro: ' . $e->getMessage());
	}
}