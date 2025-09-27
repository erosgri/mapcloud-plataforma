<?php

function novaConexao($banco = 'mapcloud')
{
    $servidor = '127.0.0.1';
    $usuario = 'root';
    $senha = 'carabina22';

    try {
        $conexao = new PDO(
            "mysql:host=$servidor;dbname=$banco",
            $usuario,
            $senha
        );
        return $conexao;
    } catch (PDOException $e) {
        die('Erro: ' . $e->getMessage());
    }
}