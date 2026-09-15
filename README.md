# Bar e Lanchonete São Jorge — Vitrine

Vitrine e cardápio digital público da Bar e Lanchonete São Jorge. A aplicação permite consultar porções, bebidas e sucos, escolher tamanhos, montar um pedido e finalizar pelo WhatsApp.

O atendimento é destinado a consumo no local ou retirada no estabelecimento. Não há delivery.

## Stack

- PHP 8.2 ou superior e Composer 2
- MySQL 8 como fonte oficial do catálogo comercial
- HTML5, CSS3 e JavaScript puro
- Apache com `mod_rewrite` e `.htaccess`
- Hospedagem compartilhada HostGator/cPanel

O projeto não utiliza framework PHP, framework front-end, Node.js ou bundler. O namespace `App\` usa PSR-4 em `app/`.

## Desenvolvimento local

```bash
composer install
composer setup
composer serve
```

`composer setup` cria o `.env` a partir de `.env.example` somente quando necessário, seleciona uma porta local livre e atualiza o autoload. Um `.env` já existente é preservado. O arquivo contém configuração local e nunca deve ser versionado.

O endereço da aplicação é exibido por `composer serve`. O servidor PHP embutido é apenas para desenvolvimento; o ambiente esperado em produção é Apache.

Mais detalhes estão em [desenvolvimento local](docs/LOCAL_DEVELOPMENT.md).

## Qualidade

```bash
composer test
composer lint
composer check
```

`composer check` valida o `composer.json`, verifica a sintaxe dos arquivos PHP e executa os testes automatizados.

## Estado atual

- A vitrine pública responsiva está implementada.
- Home promocional, cardápio compacto, detalhe do produto e pedido têm telas próprias.
- Busca, filtros por categoria, variantes, quantidades e observações funcionam no navegador.
- IDs, nomes, preços em centavos, categorias, subcategorias, status e tipo vêm do MySQL compartilhado logicamente com o sistema operacional da lanchonete.
- A conexão da vitrine é usada somente para leitura; não há migrations nem escritas no catálogo da lanchonete.
- Imagens, descrições, destaque, populares e relacionados são metadados editoriais de `config/storefront.php`.
- O padrão real `Meia: <nome base>` é agrupado em variantes apenas por igualdade normalizada e dentro da mesma subcategoria; exceções explícitas ficam em `config/storefront.php` e o formato legado `<nome base> - Meia` segue compatível.
- O pedido permanece salvo localmente no navegador com `localStorage`.
- A finalização revalida horário, produtos ativos e preços no servidor antes de gerar a URL oficial do WhatsApp.
- Nenhum pedido é persistido no sistema e não há delivery.

## Horário de funcionamento

A lanchonete funciona de quinta-feira a sábado, das 17h às 21h30, no timezone `America/Sao_Paulo`. O intervalo considera 17:00 como aberto e 21:30 como fechado.

O status “Aberto agora” ou “Fechado agora” e a próxima abertura são calculados pelo backend PHP a partir de `config/business.php`. Mesmo fora do horário, o cliente pode navegar, buscar produtos, escolher variantes, adicionar itens, alterar quantidades e observações e revisar o carrinho. Apenas a finalização pelo WhatsApp fica indisponível enquanto o estabelecimento estiver fechado.

O estado enviado ao JavaScript serve para a experiência da interface. O endpoint de checkout verifica novamente o horário no momento do POST; `disabled`, JavaScript e `localStorage` não são tratados como controles de segurança.

Domingo, segunda, terça e quarta-feira são dias fechados. Não há nesta etapa calendário administrativo, feriados automáticos ou alteração no MySQL.

## Finalização pelo WhatsApp

Em `/pedido`, o cliente escolhe **Retirada no local** ou **Consumir no local**. O navegador envia o carrinho por formulário ao endpoint `POST /checkout/whatsapp`, protegido por CSRF. A URL `wa.me` é criada exclusivamente pelo backend; o número é lido de `WHATSAPP_NUMBER` no `.env` e normalizado para dígitos.

Antes de responder, `WhatsAppCheckoutService` recarrega cada `productId` no catálogo ativo, usa o preço atual em centavos e recalcula o total. Nomes, variantes, imagens, disponibilidade e preços enviados pelo `localStorage` não são confiáveis. Se um preço mudou, o servidor devolve o carrinho sanitizado e autoritativo para conferência antes de uma nova tentativa.

A vitrine continua usando o MySQL somente para leitura. O endpoint não cria pedido, cliente, pagamento ou registro de checkout; ele apenas valida o carrinho, monta a mensagem e devolve a URL do WhatsApp. O carrinho não é apagado automaticamente após abrir o WhatsApp.

O número comercial deve ser configurado manualmente em cada ambiente. O `.env.example` contém somente `WHATSAPP_NUMBER=` e nenhuma credencial ou número real.

## Rotas

- `GET /` — home promocional.
- `GET /cardapio` — cardápio completo com busca e categorias.
- `GET /produto/{slug}` — escolha de tamanho, quantidade e observações.
- `GET /pedido` — revisão do pedido persistido no navegador.
- `POST /checkout/whatsapp` — revalidação do carrinho e geração da URL do WhatsApp.
- `GET /health` — verificação de saúde sem detalhes internos.
- Demais caminhos — página pública 404 com o status HTTP correto.

## Catálogo MySQL

A vitrine lê o mesmo schema comercial usado pelo sistema operacional, por meio de `StorefrontProductRepository` e `App\Core\Database`. Configure host, porta, banco, usuário, senha e charset exclusivamente no `.env` local; credenciais nunca devem ser versionadas.

A camada `StorefrontCatalogService` transforma os registros ativos no modelo público, preserva IDs MySQL nas variantes e resolve slugs. O carrinho usa a chave versionada `saoJorgeCartV2`; durante o checkout, cada `productId` é resolvido novamente no catálogo e os preços da interface são comparados com os valores oficiais.

O levantamento desta execução está em [docs/CATALOG_INTEGRATION.md](docs/CATALOG_INTEGRATION.md).

As rotas ficam em `routes/web.php` e seguem o fluxo `Router → Controller → View → Layout`.

## Estrutura

```text
app/                 Núcleo e controllers
bootstrap/app.php    Composição e inicialização
config/              Ambiente e cardápio temporário
database/            Estrutura para migrations e seeds futuros
docs/                Arquitetura, segurança e ambiente local
public/              Document Root e assets públicos
resources/views/     Layouts, componentes e páginas PHP
routes/web.php       Rotas HTTP
storage/             Cache e logs locais
tests/               Testes automatizados
bin/                 Comandos do projeto
deploy/hostgator/     Configuração do mirror de produção
```

## Banco de dados

`App\Core\Database` disponibiliza PDO com prepared statements nativos e `utf8mb4`. As credenciais vêm exclusivamente do ambiente. A vitrine não possui migration para o catálogo compartilhado e não deve executar comandos de escrita nesse banco.

## Segurança

- Segredos ficam somente no `.env`.
- Valores dinâmicos nas views são escapados com `e()`.
- O núcleo mantém CSRF, sessão segura, validação, logs e tratamento de erros para usos futuros.
- Uploads bloqueiam execução PHP e não são versionados.
- O número do WhatsApp não aparece no JavaScript ou nas views e vem exclusivamente do ambiente.

Consulte a política em [docs/SECURITY.md](docs/SECURITY.md) e a visão estrutural em [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Deploy HostGator

```bash
composer deploy:hostgator
```

O comando executa os checks e gera `deploy/hostgator/mirror/`, uma cópia local preparada para atualização manual na hospedagem. O mirror e suas informações locais de build permanecem fora do Git.

O processo não envia arquivos ao servidor e não inclui `.env`, configurações PHP/Apache existentes, uploads, logs ou cache. Nunca sobrescreva o `.env` de produção. As regras do servidor devem ser mescladas manualmente na primeira instalação.

As instruções completas e os cuidados para cPanel estão em [deploy/hostgator/README.md](deploy/hostgator/README.md).

## Produção

Use pelo menos:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

Instale as dependências com `composer install --no-dev --classmap-authoritative`, mantenha escrita apenas onde necessário e aponte o Document Root para `public/`. Quando isso não for possível, siga rigorosamente a estratégia de mirror documentada para a HostGator.

## Admin da Vitrine

O painel `/admin` usa as mesmas credenciais administrativas armazenadas em `admin_users` no banco compartilhado, mas mantém sessão própria da vitrine. Ele permite alterar somente `storefront_visible` de categorias, subcategorias e produtos; o campo operacional `active` é apenas leitura. Veja `docs/ADMIN.md` para detalhes de segurança e rotas.

## Funcionamento da Vitrine

O quadro de aviso bloqueante e o horário semanal editável ficam em **Admin > Funcionamento**. Antes do primeiro deploy dessa funcionalidade, aplique **composer storefront:operations-schema**. Consulte [docs/STOREFRONT_OPERATIONS.md](docs/STOREFRONT_OPERATIONS.md) para schema, precedência do checkout e procedimento de produção.