
## Passo 1: Cadastrar um Motorista

A primeira coisa a fazer é adicionar um motorista ao sistema.

1.  Abra o navegador e acesse a página inicial da aplicação (ex: `http://localhost/mapcloud-plataforma/`).
2.  Clique no botão **"Cadastrar Novo Motorista"**.
3.  Preencha o nome do motorista (ex: "Carlos Alberto") e clique em **"Cadastrar Motorista"**.
4.  Você verá uma mensagem de sucesso, e o motorista estará disponível na página inicial.

## Passo 2: Selecionar um Motorista para Monitorar

Com um motorista cadastrado, você pode acessar o painel de monitoramento dele.

1.  Volte para a página inicial.
2.  Na lista de "Motoristas Disponíveis", clique no nome do motorista que você acabou de cadastrar.
3.  Você será redirecionado para a página `monitoramento.php`, que é o painel de controle daquele motorista. Inicialmente, todos os contadores estarão zerados.

## Passo 3: Adicionar Entregas (Upload de NF-e)

Agora vamos adicionar algumas entregas à rota do motorista.

1.  No painel de monitoramento, clique no botão **"+ Adicionar Entrega"**. Um modal (janela pop-up) irá abrir.
2.  Clique em **"Escolher Arquivo"**.
3.  Navegue até a pasta `samples/` dentro do projeto.
4.  Faça um arquivo XML com um dos exemplos no bloco de notas e salve como .XML(ex: `nfe001.xml`).
5.  Clique em **"Enviar NF-e"**.
6.  A página será atualizada automaticamente, e a entrega aparecerá na lista de "Entregas" e como um ponto de coleta no mapa.
7.  Repita o processo para quantos arquivos XML desejar (ex: `nfe002.xml`, `nfe003.xml`, etc.). Cada um se tornará uma nova entrega na rota.

## Passo 4: Interagir com o Mapa e a Lista

Com as entregas carregadas, você pode visualizar os detalhes.

*   **No Mapa:** Cada entrega na fase de "Coleta" aparecerá com um ícone de caixa. Clique no ícone para ver um resumo da entrega.
*   **Na Lista:**
    *   Clique em qualquer lugar na área de uma entrega na lista (onde o cursor vira uma "mãozinha" 👆). O mapa irá centralizar e focar naquela entrega específica.
    *   Clique no ícone da seta (❯) para expandir e ver mais detalhes sobre a entrega.

## Passo 5: Atualizar o Status das Entregas

Simule o progresso do motorista atualizando o status das entregas.

1.  **Marcar como "Em Trânsito":**
    *   Na lista de entregas, selecione uma ou mais entregas marcando a caixa de seleção ao lado delas.
    *   Clique no botão **"Marcar como Em Trânsito"**.
    *   As entregas selecionadas mudarão de status, e o ícone no mapa se tornará um caminhão 🚚, agora posicionado no endereço de *destino*.

2.  **Marcar como "Entregue":**
    *   Selecione uma ou mais entregas que estão "Em Trânsito".
    *   Clique no botão **"Marcar como Entregue"**.
    *   As entregas serão movidas para a seção "Concluídas", e os KPIs (indicadores) no topo da página serão atualizados.

## Passo 6 (Opcional): Testar o Sistema de Erros

*   **NF-e Duplicada:** Tente fazer o upload do mesmo arquivo XML duas vezes. Na segunda tentativa, o sistema deve mostrar um erro informando que "Esta NF-e já foi cadastrada anteriormente". Isso demonstra que a proteção contra duplicatas está funcionando.
*   **Arquivo Inválido:** Tente enviar um arquivo que não seja `.xml`. O sistema deve impedir o upload.
