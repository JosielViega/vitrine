# Plano de estudo e revisão completa da Vitrine São Jorge

## Objetivo e modo de uso

Este plano deve permitir que um programador web entenda o sistema de ponta a ponta, consiga explicar cada decisão importante e então revise o código produzido por IA com critérios próprios. Ele não substitui a leitura do código: cada etapa aponta para arquivos, classes, métodos, rotas, tabelas e testes reais do repositório.

Ao estudar, mantenha três cadernos separados:

1. **mapa factual** — o que o código faz hoje;
2. **evidências** — qual teste, execução local ou validação de produção sustenta cada conclusão;
3. **achados** — dúvidas, riscos e melhorias, sem transformar hipótese em bug.

Não use dados reais de clientes, credenciais, hashes ou segredos. Exercícios SQL deste plano são somente de leitura. Alterações de schema devem ser feitas em banco local descartável e pelos applicators dedicados.

## Visão executiva

A Vitrine São Jorge é uma aplicação PHP 8.2+ sem framework. Composer fornece autoload PSR-4 e dependências; PDO acessa MySQL; PHP renderiza HTML no servidor; JavaScript vanilla acrescenta busca, carrinho, checkout e interações administrativas; CSS próprio atende as interfaces pública e administrativa. Em produção, o alvo documentado é Apache com `mod_rewrite` em hospedagem compartilhada HostGator/cPanel.

O catálogo não nasce de um banco exclusivo da Vitrine. Ele lê `categories`, `subcategories` e `products` do banco operacional compartilhado e acrescenta controles próprios de publicação, imagens e funcionamento. O checkout não persiste pedido: revalida o carrinho e devolve uma URL `wa.me`.

### Fluxo HTTP principal

```text
Navegador
  -> Apache / Document Root apontado para public/
  -> public/.htaccess (arquivo/diretório real ou rewrite)
  -> public/index.php (front controller)
  -> bootstrap/app.php (ambiente + composição manual)
  -> routes/web.php / App\Core\Router
  -> Controller
  -> Service
  -> Repository
  -> App\Core\Database -> PDO/MySQL
  -> Service transforma o resultado
  -> App\Core\View -> página -> layout
  -> Response -> HTML/CSS/JavaScript
```

Nem toda rota percorre todas as caixas. `/health` termina no controller; a página de aviso consulta operações e usa um layout exclusivo; a resposta de checkout é JSON.

### Fluxos laterais

```text
Admin: navegador -> sessão/CSRF -> AdminController -> Service -> Repository -> MySQL

Upload: multipart -> ProductImageProcessor -> arquivo WebP temporário/final
                                      -> ProductImageRepository -> metadata/associações
                                      -> compensações entre filesystem e banco

Checkout: localStorage -> POST + CSRF -> WhatsAppCheckoutService
         -> notice -> horário -> catálogo/preço/addons autoritativos -> URL wa.me

Deploy: source -> composer deploy:hostgator -> allowlist
       -> deploy/hostgator/mirror -> upload manual/cPanel
```

## O banco: domínio compartilhado e domínio da Vitrine

Confirme esta fronteira antes de revisar qualquer escrita:

| Estrutura | Papel | Quem a domina conceitualmente |
|---|---|---|
| `categories` | Categorias comerciais, ordem e `active` | sistema operacional compartilhado |
| `subcategories` | Subcategorias e vínculo à categoria | sistema operacional compartilhado |
| `products` | Produto operacional, preço, tipo e `active` | sistema operacional compartilhado |
| `admin_users` | Identidade e hash de senha do administrador | autenticação compartilhada; Vitrine só consulta |
| `admin_remember_tokens` | Estrutura operacional existente | não é usada pela Vitrine atual |
| `storefront_visible` nas três tabelas de catálogo | Publicação independente na Vitrine | Vitrine |
| `storefront_product_images` | Metadata de imagens gerenciadas | Vitrine |
| `storefront_product_image_products` | Associação produto real -> imagem | Vitrine |
| `storefront_settings` | Linha canônica do quadro de aviso | Vitrine |
| `storefront_business_hours` | Sete dias do calendário administrável | Vitrine |

Não confunda “banco compartilhado” com “conexão somente leitura”. O catálogo é lido, mas o Admin escreve `storefront_visible` nas tabelas compartilhadas e grava estruturas próprias de imagens e funcionamento.

## Estimativa realista de estudo

Use a faixa menor se já domina PHP/PDO/HTTP; use a maior se também executar os exercícios e produzir todos os artefatos.

| Bloco | Horas |
|---|---:|
| Ambiente e visão geral | 3–4 |
| Bootstrap, HTTP, Router, Response e View | 5–6 |
| Banco e integração com catálogo | 5–7 |
| Catálogo, agrupamento, variantes e acréscimos | 8–10 |
| Autenticação, sessão, CSRF e Admin | 6–8 |
| Imagens e uploads | 6–8 |
| Funcionamento da Vitrine | 4–6 |
| Carrinho e checkout WhatsApp | 7–9 |
| Frontend público e administrativo | 5–7 |
| Testes e leitura de evidências | 6–8 |
| Deploy/HostGator | 4–6 |
| Revisão final e artefatos | 5–7 |
| **Total estimado** | **64–86** |

Uma cadência sustentável é de 2 horas por sessão: 20 minutos de leitura orientada, 60 minutos de trace/exercício, 25 minutos de testes e 15 minutos de registro de conclusões.

## Mapa do repositório

| Caminho | Função | Entra no runtime? | Prioridade |
|---|---|---:|---:|
| `public/index.php` | front controller: carrega bootstrap, rotas, dispatch e send | sim | 1 |
| `public/.htaccess` | rewrite para `index.php`, desabilita índice e protege nomes sensíveis | sim no Apache | 1 |
| `bootstrap/app.php` | composição manual das dependências por request | sim | 1 |
| `routes/web.php` | tabela de rotas e ligação controller/método | sim | 1 |
| `app/Core/` | HTTP, ambiente, PDO, sessão, CSRF, view, log e erros | sim | 1 |
| `app/Controllers/` | fronteira HTTP, auth, CSRF, redirects e respostas | sim | 1 |
| `app/Services/` | regras, transformação e orquestração de domínio | sim | 1 |
| `app/Repositories/` | SQL e transações | sim | 1 |
| `app/Helpers/functions.php` | `env()` e escape `e()` | sim via Composer | 1 |
| `app/Validation/` | validador genérico disponível | sim, embora não componha os fluxos principais atuais | 3 |
| `config/` | configuração de app, banco, calendário inicial, catálogo e WhatsApp | sim | 1 |
| `resources/views/` | layouts, páginas e componentes PHP | sim | 1 |
| `public/assets/js/` | comportamento público e Admin | sim no navegador | 1 |
| `public/assets/css/` | estilos público e Admin | sim no navegador | 2 |
| `public/assets/images/` | imagens editoriais/fallback versionadas | sim | 2 |
| `public/uploads/` | arquivos gerenciados persistentes e regra anti-PHP | sim; conteúdo não versionado | 1 |
| `storage/logs/` | logs diários do runtime | sim; não versionado | 2 |
| `storage/cache/` | área persistente reservada | não usada diretamente no HEAD | 3 |
| `database/patches/` | SQL revisável das extensões da Vitrine | instalação/operação, não request | 1 |
| `database/migrations/` | diretório reservado; não há runner genérico para estas features | não no request | 3 |
| `bin/apply-storefront-*.php` | applicators idempotentes dedicados | operação manual | 1 |
| `bin/serve.php`, `setup.php`, `port-*.php` | laboratório local e registro de porta | desenvolvimento | 2 |
| `bin/build-hostgator-mirror.php` e `bin/lib/HostgatorMirrorBuilder.php` | build local do mirror | deploy | 1 |
| `tests/` | testes PHPUnit, fakes e contratos estáticos | não em produção | 1 |
| `docs/` | documentação temática | não | 2 |
| `deploy/hostgator/` | manifesto, manual e exemplos de regras | build/deploy | 1 |
| `composer.json` / `composer.lock` | requisitos, scripts e versões reproduzíveis | sim | 1 |
| `.env.example` | contrato sem segredos para ambiente local | referência | 1 |

### Ordem mínima para a primeira leitura

1. `README.md`, `composer.json` e `.env.example`.
2. `public/index.php`, `bootstrap/app.php` e `routes/web.php`.
3. `Request`, `Router`, `Response`, `View`, `Database`, `Session` e `Csrf`.
4. Um fluxo público completo.
5. Um fluxo administrativo completo.
6. Patches e repositories.
7. JavaScript e views correspondentes.
8. Testes da funcionalidade estudada.
9. Documentação temática e deploy.

## Modelo mental das camadas

### 1. Entrada e bootstrap

Leia nesta ordem:

1. `public/.htaccess` — requests para arquivos inexistentes chegam ao front controller;
2. `public/index.php` — apenas três operações: bootstrap, rotas, dispatch/send;
3. `Environment::load()` — lê `.env`, exige UTF-8, remove BOM e usa repositório imutável do phpdotenv;
4. `composer.json` — `App\` aponta para `app/` e helpers são carregados como arquivo;
5. `bootstrap/app.php` — instancia e conecta todos os objetos;
6. `config/*.php` — converte ambiente em arrays tipados por convenção.

Conceitos aplicados: front controller, autoload PSR-4, composição manual, dependency injection por construtor e ciclo de vida por request. Não há container de DI nem singleton global. O array `$app` é o registro de objetos daquela requisição.

O bootstrap ainda registra `ErrorHandler`, define timezone, inicia sessão com `HttpOnly`, `SameSite=Lax`, `use_strict_mode` e `use_only_cookies`, cria CSRF e PDO. `cookie_secure` fica verdadeiro se a configuração exigir ou se URL/request indicar HTTPS.

**Perguntas de revisão**

- Por que `StorefrontOperationsService` precisa existir antes de `BusinessHoursService`?
- Quais objetos abrem conexão somente quando `Database::connection()` é chamado?
- Que falha ocorre se o schema de operações ainda não existe?
- Por que `.env` não é carregado com `parse_ini_file()`?

### 2. Roteamento e controllers

`Router::add()` normaliza caminhos, converte `{slug}` em grupo nomeado e separa rotas por método. `dispatch()` compara o path, passa parâmetros ao callable e exige um `Response`. O fallback de `routes/web.php` renderiza 404.

| Controller | Responsabilidade HTTP |
|---|---|
| `HomeController` | quatro páginas públicas e substituição server-side pelo aviso |
| `AdminAuthController` | formulário/login/logout, mensagens genéricas, 303 e no-store |
| `AdminController` | auth/CSRF das ações, seções, PRG, JSON da visibilidade e flashes |
| `WhatsAppCheckoutController` | CSRF, chamada do service, remoção de `http_status` e JSON |
| `HealthController` | JSON mínimo `{"status":"ok"}` |

Estude GET versus POST, parâmetro de rota, 404, 401/403/409/422/500/503, redirect 303, padrão Post/Redirect/Get e `Cache-Control: no-store`. Controller interpreta HTTP; não deve reconstruir regras de catálogo, preço, calendário ou imagem.

### 3. Services

| Service | Regra e transformação real |
|---|---|
| `StorefrontCatalogService` | lê três coleções, filtra, agrupa, indexa variantes, resolve imagem/editorial e addons |
| `StorefrontHomeHighlightsService` | valida e resolve os slugs persistidos de Destaque da casa e Mais pedidos contra o catálogo público atual |
| `StorefrontProductGroupingService` | slug, `Meia: X`, legado `X - Meia`, alias e isolamento por subcategoria |
| `StorefrontVisibilityService` | status efetivo no Admin e filtragem de descendentes por visibilidade do pai |
| `AdminAuthService` | dummy hash, `password_verify`, rate limit por sessão, regeneração e identidade mínima |
| `ProductImageService` | grupos públicos, pipeline arquivo+banco, substituição, compensação e limpeza |
| `ProductImageProcessor` | upload, MIME real, dimensões/memória, orientação, resize e WebP |
| `ProductImageStorage` | path aleatório controlado, diretório físico e remoção confinada |
| `StorefrontOperationsService` | notice, sete dias, validação, dashboard e composição do calendário efetivo |
| `BusinessHoursService` | aberto/fechado, limites, próxima abertura e textos de status |
| `WhatsAppCheckoutService` | precedência, validação autoritativa, total, mensagem e URL `wa.me` |

Regra de domínio fica em service para ser reutilizável fora de HTML/HTTP e testável com fakes. View não deve decidir se preço é válido; repository não deve decidir qual mensagem de checkout apresentar.

### 4. Repositories

| Repository | Leitura/escrita/transação |
|---|---|
| `StorefrontProductRepository` | três SELECTs públicos; joins exigem `active=1` e `storefront_visible=1` na hierarquia |
| `StorefrontVisibilityRepository` | listas administrativas e três UPDATEs allowlisted com prepared statement |
| `StorefrontProductImageRepository` | leitura administrativa, metadata, associações, locks e transações |
| `AdminAuthRepository` | SELECT preparado por username; não escreve credenciais |
| `StorefrontOperationsRepository` | settings, sete dias, UPDATE do notice e transação do calendário inteiro |

PDO usa exceptions, fetch associativo, prepared statements nativos (`ATTR_EMULATE_PREPARES=false`) e charset validado. Diferencie queries estáticas sem entrada externa (`query`) de valores não confiáveis vinculados (`prepare`/`execute`).

### 5. Apresentação

`View::render()` resolve page e layout por `realpath`, captura ambos com output buffering e usa `extract(..., EXTR_SKIP)`. A função `e()` chama `htmlspecialchars` com `ENT_QUOTES | ENT_SUBSTITUTE` e UTF-8.

Leia `layouts/app.php`, `layouts/admin.php`, `layouts/notice.php`, depois pages e components usados por cada fluxo. Dados são server-rendered em texto, atributos e `data-*`; o JS lê esses contratos. Marcação criada pelo carrinho usa `textContent`, não HTML administrável.

“Progressive enhancement” aqui é parcial: navegação e leitura do catálogo funcionam sem JS; formulários Admin tradicionais continuam enviáveis; busca, montagem do carrinho, quantidade e checkout dependem de JS. O formulário de produto não tem `action`/`method` que implementem fallback de compra sem JS.

## Fase 0 — laboratório local

### Leitura

`README.md` -> `.env.example` -> `composer.json`/`composer.lock` -> `docs/LOCAL_DEVELOPMENT.md` -> `bin/setup.php` -> `bin/serve.php` -> `bin/lib/PortRegistry.php` -> `config/database.php`.

Dependências diretas travadas no lock atual: `vlucas/phpdotenv 5.7.0` e `phpunit/phpunit 11.5.56` (dev). O projeto declara PHP `^8.2`, PDO e PDO MySQL. Upload também depende em runtime de Fileinfo e GD/WebP; EXIF é opcional no código.

### Comandos reais

```bash
composer install
composer setup
composer serve
composer port:status
composer lint
composer test
composer check
```

`composer setup` preserva `.env` existente, escolhe/reserva porta e atualiza autoload. `composer serve` usa servidor PHP embutido e `bin/server-router.php`; produção usa Apache. O banco local é um MySQL configurado por `.env`, não SQLite nem banco fake da aplicação.

### Checklist do laboratório

- [ ] PHP 8.2+ e Composer 2 confirmados.
- [ ] `pdo` e `pdo_mysql` habilitados.
- [ ] Fileinfo e GD com WebP avaliados antes de testar upload.
- [ ] EXIF anotado como melhoria opcional de orientação.
- [ ] Document Root entendido como `public/`.
- [ ] `.env` criado localmente e fora do Git, sem registrar segredos nas anotações.
- [ ] Porta consultada pelo registro, sem matar processo alheio.
- [ ] `storage/logs` e `public/uploads/products` graváveis localmente.
- [ ] `composer check` passa antes de qualquer experimento.

**Exercício:** descreva por que mudar `APP_PORT` não muda o deploy HostGator e por que o servidor embutido precisa de um router próprio.

## Fase 1 — seguir uma requisição pública

### Trace A: `GET /cardapio`

```text
Request::capture()
 -> Router encontra GET /cardapio
 -> HomeController::cardapio()
 -> blockedResponse() consulta StorefrontOperationsService::isBlocked()
 -> StorefrontCatalogService::catalog()
 -> StorefrontProductRepository::{activeCategories,activeSubcategories,activeProducts}
 -> StorefrontCatalogService::transform()
 -> StorefrontProductGroupingService::group()
 -> View::render('pages/menu', ..., 'layouts/app')
 -> Response::html() -> send()
```

Siga um nome desde `products.name`, pelo array de produto público, `data-search-name`, até `normalizeText()` em `app.js`. Registre origem, tipo, filtros, transformação e o ponto onde `e()` protege HTML.

### Trace B: `GET /produto/{slug}`

O slug vem da URI, passa por `rawurldecode`, é comparado com IDs públicos já gerados e não entra em SQL. Produto inexistente renderiza 404; existente entrega variantes reais (`product_id`), addons elegíveis, imagem e relacionados para `pages/product.php`.

**Exercício:** desenhe ambos os fluxos sem consultar esta seção. Marque com cores diferentes entrada não confiável, dado do banco, dado editorial e dado escapado.

**Perguntas**

- Quantas consultas o primeiro `catalog()` faz por request?
- Por que `cachedCatalog` não é cache entre requests?
- O aviso é decidido antes ou depois de carregar o catálogo?
- Como colisões de slug em subcategorias distintas são resolvidas?

## Fase 2 — banco e integração operacional

Leia os patches em ordem:

1. `001_add_storefront_visibility.sql` — acrescenta `storefront_visible` às três tabelas existentes;
2. `002_add_storefront_product_images.sql` — cria metadata e associação sem FK para `products`;
3. `003_add_storefront_operations.sql` — cria settings e calendário semanal.

Depois compare cada patch ao applicator em `bin/`. Eles são comandos dedicados e não usam tabela `migrations`.

### `active` versus `storefront_visible`

- `active`: disponibilidade operacional controlada pelo sistema compartilhado;
- `storefront_visible`: intenção de publicação controlada pela Vitrine.

Para aparecer publicamente, produto, subcategoria e categoria precisam estar ativos e visíveis. No Admin, a categoria continua listada mesmo oculta; subcategorias só aparecem quando a categoria está visível; produtos só aparecem quando categoria e subcategoria estão visíveis. O próprio item oculto continua listado para reativação. Não existe cascade: ocultar pai preserva flags dos filhos.

### SQL seguro para estudo

```sql
SELECT id, name, active, storefront_visible
FROM categories
ORDER BY sort_order, name;

SELECT p.id, p.name, p.price_cents, p.active, p.storefront_visible,
       s.name AS subcategory, c.name AS category
FROM products p
JOIN subcategories s ON s.id = p.subcategory_id
JOIN categories c ON c.id = s.category_id
ORDER BY c.sort_order, s.sort_order, p.name;

SELECT weekday, enabled, open_time, close_time
FROM storefront_business_hours
ORDER BY weekday;
```

Não execute `UPDATE`, `DELETE`, `ALTER` ou applicator em produção durante estudo. Use backup e autorização formal para qualquer mudança.

**Exercício:** produza um diagrama que separe tabelas operacionais, colunas adicionadas pela Vitrine e tabelas exclusivas da Vitrine.

## Fase 3 — catálogo, agrupamento e variantes

Leia `StorefrontProductRepository`, `StorefrontCatalogService`, `StorefrontProductGroupingService`, `config/storefront.php` e seus testes.

O repository entrega linhas operacionais. O grouping trabalha dentro de cada subcategoria e reconhece:

- inteira: nome base, por exemplo `Camarão c/ Batata e Aipim`;
- meia: prefixo `Meia: Camarão c/ Batata e Aipim`;
- legado: sufixo `<nome base> - Meia`;
- exceção explícita: alias `porcao-carne -> porcao-de-carne`.

Não existe fuzzy matching. Meia órfã continua como produto público com variante `Meia`; inteira órfã vira variante sem rótulo. Produto operacional e produto público não são a mesma entidade: duas linhas/IDs/preços podem formar um produto público/slug.

### Trace de uma porção Inteira/Meia

```text
products (duas linhas reais)
 -> repository preserva IDs e preços
 -> group() encontra mesmo nome-base na mesma subcategoria
 -> primary_row + secondary_row
 -> variants [{product_id, label=Inteira}, {product_id, label=Meia}]
 -> produto público com um slug
 -> pages/product.php envia o ID real da variante escolhida
 -> checkout resolve novamente esse ID real
```

`StorefrontCatalogService` também resolve categorias/subcategorias, descrição editorial, fallback de imagem, imagem gerenciada e relacionados. Ao final remove subcategorias que ficaram sem produtos. Featured e populares efetivos são resolvidos por `StorefrontHomeHighlightsService` a partir da tabela `storefront_home_highlights`; os valores em `config/storefront.php` são somente seed/default de primeira instalação.

**Exercícios**

- Encontre no teste o caso que proíbe agrupamento entre subcategorias.
- Explique por que os destaques persistidos e os relacionados usam slugs públicos, não IDs de variantes.
- Simule no papel o que ocorre se duas subcategorias gerarem o mesmo slug público.

## Fase 4 — acréscimos

Em `config/storefront.php`, os addons atuais são:

| Nome operacional | ID real | Elegibilidade |
|---|---:|---|
| Bacon | 117 | produtos públicos da subcategoria normalizada `porcoes` |
| Mussarela | 74 | produtos públicos da subcategoria normalizada `porcoes` |

Eles continuam sendo linhas em `products`, mas `StorefrontCatalogService::transform()` os separa de `publishableRows`; portanto não entram como produtos compráveis, featured, populares ou relacionados. Somente addons ativos/visíveis disponíveis são anexados às porções elegíveis.

### Trace: Inteira + Bacon + Mussarela, quantidade 2

```text
pages/product.php -> checkboxes independentes
 -> app.js normalizeAddons() ordena por productId
 -> cartLineKey = variante + produto público + IDs de addons + observação normalizada
 -> localStorage saoJorgeCartV2
 -> POST /checkout/whatsapp
 -> findVariantByProductId(ID da Inteira)
 -> authoritativeAddons() compara IDs com addons do produto público
 -> preço unitário = base + Bacon + Mussarela
 -> total da linha = preço unitário × 2
 -> mensagem lista addons e informa “por unidade”
```

Combinações ou observações diferentes geram linhas diferentes; quantidades só se somam quando `cartLineKey` coincide. Nomes e preços enviados pelo navegador não são autoridade.

**Exercício:** use valores fictícios de R$ 50,00 + R$ 6,00 + R$ 6,00 e demonstre por que o total é `(5000 + 600 + 600) × 2 = 12400` centavos, e não `5000 × 2 + 600 + 600`.

## Fase 5 — Admin, autenticação, sessão e CSRF

Leia `AdminAuthRepository`, `AdminAuthService`, `AdminAuthController`, `Session`, `Csrf`, `admin/login.php`, `layouts/admin.php` e `AdminAuthServiceTest`.

### Trace completo

```text
GET /admin/login
 -> cria/reutiliza token CSRF na sessão
 -> renderiza formulário com no-store

POST /admin/login
 -> valida CSRF, formato e limites
 -> AdminAuthRepository::findByUsername() preparado
 -> password_verify() sempre executa (DUMMY_HASH se usuário não existe)
 -> registra tentativa inválida na sessão ou aplica limite 5/600s
 -> sessão precisa regenerar antes de gravar identidade mínima
 -> 303 /admin ou 303 /admin/login com flash genérico

GET /admin
 -> check() exige flag, userId e username consistentes

POST /admin/logout
 -> auth + CSRF
 -> remove só chaves Admin/tentativas
 -> regenera sessão
 -> preserva outros dados de sessão
```

O rate limit atual é por sessão, não por IP/conta no banco. Isso reduz abuso acidental, mas deve ser avaliado contra o modelo de ameaça real.

### Caso de produção: cookie Secure e HTTP

```text
SESSION_SECURE=true + acesso HTTP
 -> Set-Cookie marca Secure
 -> navegador não devolve cookie por HTTP
 -> request seguinte cria outra sessão
 -> token submetido não existe nessa nova sessão
 -> CSRF falha
 -> HTTP 403 “Acesso negado.”

HTTPS
 -> cookie Secure volta ao servidor
 -> mesma sessão/token
 -> login funciona
```

Esta é uma lição de infraestrutura e aplicação: não “corrija” desativando Secure em produção; conclua DNS/AutoSSL/HTTPS e teste a URL correta.

**Checklist de segurança**

- [ ] Senha nunca é guardada em sessão/log/view.
- [ ] Mensagem pública não distingue usuário inexistente de senha errada.
- [ ] Regeneração ocorre antes de autenticar.
- [ ] POSTs mutáveis exigem auth e CSRF.
- [ ] Admin responde com no-store.
- [ ] Cookie tem HttpOnly, SameSite e Secure sob HTTPS.

## Fase 6 — visibilidade administrativa

Leia `StorefrontVisibilityRepository`, `StorefrontVisibilityService`, `AdminController::updateVisibility()`, `components/admin-visibility-form.php`, `admin.js` e os três grupos de testes de visibilidade.

Matriz de estudo:

| Item | Aparece na própria aba quando oculto? | Descendentes exibidos no Admin? | Altera `active`? |
|---|---:|---:|---:|
| Categoria | sim | não | não |
| Subcategoria | sim, se pai visível | produtos não | não |
| Produto | sim, se ancestrais visíveis | n/a | não |

### Regressão real: checkbox e `FormData`

Controles `disabled` não participam de `FormData`. A implementação correta captura o estado, cria `new FormData(form)`, garante `formData.set('visible', ...)` e só então desabilita o toggle durante o request. Leia `AdminSecurityTest::testVisibilityRequestCapturesCheckedStateBeforeDisablingToggle()`.

**Exercício:** abra DevTools em ambiente local, altere visibilidade e relacione payload, resposta JSON, redirect e novo status renderizado. Restaure o valor ao final.

## Fase 7 — imagens e upload

Leia StorefrontProductImageRepository, ProductImageService, ProductImageProcessor, ProductImageStorage, 002_add_storefront_product_images.sql, o applicator e docs/PRODUCT_IMAGES.md.

### Modelo e pipeline

- uma imagem gerenciada pode ser associada a todos os IDs reais de um produto público agrupado;
- o arquivo não é BLOB: metadata fica em storefront_product_images e a associação em storefront_product_image_products;
- o arquivo WebP fica sob /uploads/products/YYYY/MM/<32 hex>.webp;
- o catálogo prefere imagem gerenciada, depois editorial, depois fallback por categoria/global.

ProductImageProcessor valida erro de upload e 8 MB, detecta MIME com Fileinfo, aceita JPEG/PNG/WebP, limita lados/pixels/memória, aplica EXIF quando disponível, não faz upscale, preserva alpha e gera WebP com qualidade 82 e lado máximo 1600.

ProductImageStorage::physicalPath() rejeita NUL, .., backslash, path/extensão arbitrários; remove() confirma realpath dentro do diretório-base. Nomes vêm de 16 bytes aleatórios.

### Banco e filesystem não formam uma única transação

Estude a ordem e as compensações:

| Falha/momento | Estado esperado no banco | Estado esperado no filesystem |
|---|---|---|
| validação/processamento falha | nenhuma escrita | temporário removido; arquivo anterior preservado |
| rename temporário -> final falha | nenhuma escrita | sem arquivo final válido |
| arquivo final pronto, transação DB falha | associações antigas preservadas pelo rollback | novo arquivo removido |
| DB confirma nova associação | aponta para nova imagem | novo arquivo existe |
| limpeza do arquivo antigo falha | metadata antiga é preservada de forma conservadora | arquivo antigo pode permanecer para investigação |
| remoção de grupo confirma DB | associações removidas | arquivo só é removido se nenhuma associação restante |
| imagem antiga ainda compartilhada | associação de outro produto preservada | arquivo preservado |

No repository, FOR UPDATE, transação, ON DUPLICATE KEY UPDATE e deleção condicional evitam parte das corridas. No service, compensações tratam a fronteira não transacional entre MySQL e disco.

**Exercícios**

- Reproduza apenas em storage temporário um JPEG, PNG transparente e WebP.
- Explique os testes de database failure e shared old image.
- Desenhe dois timelines concorrentes substituindo a mesma imagem e marque o que ainda precisa ser avaliado.

## Fase 8 — funcionamento da Vitrine

Leia 003_add_storefront_operations.sql, apply-storefront-operations-schema.php, StorefrontOperationsRepository, StorefrontOperationsService, BusinessHoursService, a seção Operations de AdminController/admin/index.php e docs/STOREFRONT_OPERATIONS.md.

### Persistência e seed

- storefront_settings: linha canônica id=1, notice ativo, título e mensagem;
- storefront_business_hours: weekdays ISO 1–7, enabled e horários;
- config/business.php: timezone e seed/default inicial;
- applicator: cria tabelas ausentes e usa ON DUPLICATE KEY UPDATE inócuo para não sobrescrever edição.

O calendário efetivo depois da instalação vem do banco. StorefrontOperationsService::businessHoursService() converte TIME (HH:MM:SS) para o contrato HH:MM e instancia BusinessHoursService, que continua responsável por abertura, fechamento e próxima abertura.

### Validação e integridade

updateBusinessHours() valida os sete dias antes de chamar o repository: dia aberto exige HH:MM e open < close; dia fechado vira NULL/NULL. Só depois StorefrontOperationsRepository::updateBusinessHours() inicia transação e atualiza todos.

### Precedência

~~~text
notice ativo
 -> HomeController entrega somente pages/notice + layouts/notice + no-store
 -> WhatsAppCheckoutService retorna storefront_blocked

notice inativo + fechado
 -> catálogo continua navegável
 -> checkout retorna business_closed

notice inativo + aberto
 -> navegação e checkout normais
~~~

Trace do notice: POST /admin/operations/notice -> auth/CSRF -> validação/trim/UTF-8/limites -> UPDATE preparado -> 303 -> nova request -> HomeController::blockedResponse() -> texto escapado -> layout sem JS/navegação -> no-store.

**Perguntas**

- Por que /health e /admin não passam por blockedResponse()?
- Quantas vezes settings/schedule são consultados ao montar o dashboard?
- Como BusinessHoursService::scheduleHours() se comporta se dias tiverem horários diferentes?
- O que deve acontecer se uma das sete linhas estiver ausente?

## Fase 9 — carrinho e checkout WhatsApp

Esta fase merece um trace campo a campo. Leia pages/product.php, app.js, pages/order.php, WhatsAppCheckoutController, WhatsAppCheckoutService, config/whatsapp.php e os testes de checkout/addons.

### Contrato do navegador

saoJorgeCartV2 guarda linhas com productId, publicProductId, nome, variante, centavos, quantidade, addons, observação e imagem. cartLineKey() combina ID real, slug público, IDs de addons ordenados e observação normalizada. Assim, duas configurações da mesma porção podem coexistir.

localStorage é persistência de conveniência, não fonte de verdade. O usuário pode editar todo o conteúdo.

### Trace completo

~~~text
pages/product.php renderiza IDs/preços atuais
 -> initProduct() escolhe variante/addons/quantidade/notas
 -> normalizeAddons() + cartLineKey()
 -> saveCart() em saoJorgeCartV2
 -> GET /pedido renderiza status e token CSRF
 -> initOrder() monta DOM com textContent e calcula prévia
 -> POST /checkout/whatsapp (form-urlencoded + Accept JSON)
 -> WhatsAppCheckoutController valida CSRF
 -> WhatsAppCheckoutService verifica notice
 -> BusinessHoursService verifica horário
 -> valida número e service_type
 -> decodifica JSON, limites e formato
 -> findVariantByProductId() recarrega catálogo público
 -> authoritativeAddons() valida elegibilidade/IDs/preços
 -> recalcula (base + addons) × quantidade
 -> sanitiza observação
 -> compara preços submetidos
 -> monta mensagem e rawurlencode
 -> JSON com https://wa.me/... ou erro
~~~

### Códigos principais

| Código | HTTP atual | Significado |
|---|---:|---|
| invalid_csrf | 403 | sessão/token não conferem |
| storefront_blocked | 409 | notice tem precedência e não gera URL |
| business_closed | 409 | estabelecimento fechado |
| cart_invalid | 422 ou 409 | estrutura inválida (422) ou item indisponível (409) |
| cart_changed | 409 | preço base/addon divergente; devolve carrinho autoritativo |
| cart_empty | 422 | nenhuma linha |
| invalid_service_type | 422 | não é retirada nem consumo local |
| whatsapp_not_configured | 503 | número ausente/inválido |
| unexpected_error | 500 | exceção inesperada, registrada sem detalhe ao cliente |

Leia o método error() para confirmar status por chamada; o mesmo code pode aparecer em mais de um status.

**Exercícios**

- Manipule preço apenas em fixture/teste e siga cart_changed até saveCart(result.cart).
- Explique por que a URL só é aceita pelo JS se começar com https://wa.me/.
- Liste quais campos do carrinho são usados para autoridade e quais são descartados/recalculados.

## Fase 10 — frontend

Leia por funcionalidade, não por tamanho de arquivo:

| Área | View/component | JS | CSS |
|---|---|---|---|
| Home | home.php, service-info, cards, bottom nav | indicador do carrinho | app.css |
| Cardápio | menu.php, product-list-item | busca, categoria/hash | app.css |
| Produto | product.php | quantidade, addons e persistência | app.css |
| Pedido | order.php | render DOM, quantidade, tipo e fetch | app.css |
| Admin | admin/index.php, visibility form | busca, upload, visibilidade, horários | admin.css |
| Notice | notice.php, layouts/notice.php | nenhum JS | app.css |

O CSS público contém breakpoints pequenos e desktop, focus states e prefers-reduced-motion: reduce. O Admin reorganiza grids em 920/640/380 px. Confirme comportamento real em teclado, leitor de tela e viewport; presença de aria-* não prova acessibilidade completa.

Pontos concretos para revisar:

- navegação continua útil sem JS, mas carrinho/checkout não;
- busca usa texto normalizado e hidden;
- DOM do pedido usa textContent;
- radio/checkbox têm label; botões de ícone têm aria-label;
- disabled é UX, nunca guarda server-side;
- horários fechados desabilitam inputs no frontend, mas backend ignora campos de dias fechados;
- prefers-reduced-motion existe em app.css; confirme se cobre toda animação relevante;
- admin.css não declara bloco equivalente no HEAD atual.

## Fase 11 — testes

PHPUnit usa phpunit.xml, autoload de dev Tests e fakes em memória. A suíte atual não conecta ao MySQL real: testes de schema inspecionam SQL/scripts; testes de repository frequentemente inspecionam strings/contratos; rotas são despachadas com fixtures.

| Arquivo | Tipo | Cobertura principal | Banco real? | Filesystem? | Risco de dados |
|---|---|---|---:|---:|---:|
| AdminAuthServiceTest.php | unit | senha, dummy hash, regeneração, logout, rate limit | não | não | nenhum |
| AdminRouteTest.php | route/integration com fakes | auth, CSRF, imagens, Operations, PRG | não | temp/log | temporário |
| AdminSecurityTest.php | contract/static | repository read-only, rotas POST, FormData | não | lê fontes | nenhum |
| BusinessHoursServiceTest.php | unit | limites, timezone, próxima abertura | não | não | nenhum |
| CsrfTest.php | unit | criação e verificação do token | não | não | sessão global de teste |
| EnvironmentTest.php | unit/integration | UTF-8, BOM e entrypoints | não | temp | temporário |
| HelpersTest.php | unit | escape e() | não | não | nenhum |
| HostgatorDeployManifestTest.php | contract | allowlist/proteções | não | lê config | nenhum |
| HostgatorMirrorBuilderTest.php | integration filesystem | mirror válido/protegido/vendor dev | não | temp | temporário |
| PortRegistryTest.php | unit/integration filesystem | reservas, corrupção e release | não | temp | temporário |
| PortTest.php | unit/socket | listener e próxima porta | não | não | abre socket local |
| ProductImageProcessorTest.php | unit/integration GD | MIME, resize, alpha, limites, pixel bomb | não | temp | temporário |
| ProductImageServiceTest.php | unit com fakes | upload/substituição/compensação/remoção | não | fakes/temp | temporário |
| ProjectSetupTest.php | integration filesystem | .env, porta e idempotência | não | temp | temporário |
| RouterTest.php | unit | parâmetro, fallback e method override | não | não | nenhum |
| StorefrontAddonTest.php | unit/contract/view | exclusão, elegibilidade e checkboxes | não | lê view/JS | nenhum |
| StorefrontCatalogServiceTest.php | unit/contract | agrupamento, aliases, active e SQL | não | não | nenhum |
| StorefrontOperationsSchemaTest.php | schema contract | duas tabelas, seed/idempotência textual | não | lê fontes | nenhum |
| StorefrontOperationsServiceTest.php | unit | notice, sete dias, validação e status | não | não | nenhum |
| StorefrontProductGroupingServiceTest.php | unit | grupos Admin e alias | não | não | nenhum |
| StorefrontProductImagesTest.php | unit/schema/contract | precedência, schema, storage e repository | não | temp/lê fontes | temporário |
| StorefrontTest.php | route/view contract | páginas, fechado, notice, JS e XSS | não | lê JS/views | nenhum |
| StorefrontVisibilitySchemaTest.php | schema contract | patch/applicator | não | lê fontes | nenhum |
| StorefrontVisibilityServiceTest.php | unit | Admin, hierarchy, sem cascade | não | não | nenhum |
| StorefrontVisibilityTest.php | unit/contract | publicação e checkout de variante oculta | não | não | nenhum |
| ValidatorTest.php | unit | regras genéricas | não | não | nenhum |
| WhatsAppCheckoutRouteTest.php | route | JSON, CSRF, fechado e addon | não | temp/log | temporário |
| WhatsAppCheckoutServiceTest.php | unit | validações, preços, addons, mensagem e precedência | não | não | nenhum |

Comandos:

~~~bash
composer validate --strict
composer lint
composer test
composer check
~~~

Teste passando não prova requisito correto, integração MySQL/MariaDB, permissões reais, comportamento do Apache, upload na HostGator ou UX visual. Para cada requisito, produza a cadeia:

~~~text
requisito -> implementação -> teste automatizado -> execução local -> evidência de produção
~~~

**Exercício:** escolha “notice tem precedência sobre horário” e cite método, testes, uma chamada HTTP local controlada e a evidência de produção disponível (ou marque a lacuna).

## Fase 12 — deploy HostGator e produção

Leia deploy/hostgator/README.md, deploy/hostgator/config/deploy.php, bin/build-hostgator-mirror.php, HostgatorMirrorBuilder, testes do mirror, exemplos de regras e .gitignore.

~~~text
source versionado
 -> composer deploy:hostgator
 -> composer check
 -> cópia da allowlist
 -> composer install --no-dev --classmap-authoritative no mirror
 -> validação de caminhos/dependências
 -> build-info.json local
 -> deploy/hostgator/mirror
 -> upload manual no cPanel
~~~

O builder não envia, não apaga remotamente e não executa schema. vendor é reconstruído a partir de composer.lock, em vez de copiar dependências de desenvolvimento.

Preserve no servidor:

- .env e configuração Apache/cPanel (.htaccess, .user.ini, php.ini);
- public/uploads;
- storage/logs e storage/cache;
- certificados, backups e diretórios da conta.

Document Root deve preferencialmente apontar para public/. Regras de server-config-examples são mescladas; nunca substitua cegamente regras da hospedagem.

### Caso real de infraestrutura para estudar

~~~text
domínio recém-registrado
 -> DNS/nameservers em transição
 -> aplicação ainda inacessível
 -> resolução DNS estabiliza
 -> AutoSSL emite/instala certificado
 -> HTTPS passa a responder
 -> cookie Secure volta ao servidor
 -> sessão/CSRF permanecem estáveis
 -> Admin funciona
~~~

O contexto do projeto informa publicação na HostGator. O Git comprova código, build e documentação; não é, sozinho, evidência de que cada feature atual foi validada no ambiente vivo.

**Exercício:** gere e inspecione um mirror local. Não faça upload. Compare a allowlist com tudo que uma primeira instalação das features atuais precisa.
## Níveis de evidência: não misture

| Nível | Significa | Exemplo de evidência |
|---|---|---|
| **Implementado** | código existe no HEAD | método/rota/schema localizável |
| **Testado automaticamente** | teste cobre cenário com seu tipo e limites | PHPUnit/contract test passando |
| **Testado localmente** | execução controlada confirmou integração local | log de comando, HTTP local, banco local restaurado |
| **Validado em produção** | HostGator real confirmou comportamento | registro de smoke test datado, sem segredo |

Nunca promova evidência por inferência. Um teste com fake não é integração MySQL; mirror válido não é deploy; deploy feito não prova checkout; código de upload não prova GD/permissão/Apache reais.

Mantenha uma matriz por feature com colunas para os quatro níveis, data, ambiente, responsável e link para evidência segura.

## Revisão por fluxo

Analise um fluxo por vez:

1. Home.
2. Cardápio.
3. Busca.
4. Produto simples.
5. Produto Inteira/Meia.
6. Porção com Bacon.
7. Porção com Bacon + Mussarela.
8. Adicionar ao carrinho.
9. Carrinho com duas configurações distintas.
10. Checkout fora do horário.
11. Checkout aberto.
12. cart_changed.
13. cart_invalid.
14. Login Admin.
15. Logout Admin.
16. Ocultar categoria.
17. Reativar categoria.
18. Ocultar subcategoria.
19. Ocultar produto.
20. Upload de imagem.
21. Substituição da imagem.
22. Remoção da imagem.
23. Alterar horário.
24. Ativar quadro de aviso.
25. Tentar checkout com quadro ativo.
26. Desativar aviso.
27. /health.
28. Primeira instalação de schema.
29. Applicator idempotente.
30. Deploy HostGator.

### Ficha obrigatória

~~~text
Fluxo:
Ator:
Rota/método:
Entrada não confiável:
Validação:
Autenticação/autorização:
Controller:
Service:
Repository:
SQL:
Arquivos:
Transação:
Resposta HTTP:
View/JSON:
Estado alterado:
Falhas:
Logs:
Teste:
Evidência:
Lacunas:
Minha conclusão:
~~~

Não preencha SQL ou transação por obrigação: /health, por exemplo, não usa nenhum. O valor da ficha está em tornar explícita a ausência.

## Método de revisão de código produzido por IA

Faça quatro passagens separadas. Misturá-las favorece comentários superficiais.

### A — correção funcional

- Reconstrua requisito e critérios sem confiar no nome do método.
- Trace sucesso, vazio, limite e falha.
- Confira precedência: notice, horário, catálogo e preço.
- Compare centavos, quantidade, variantes e addons.
- Verifique HTTP, PRG, flashes, JSON e UX com/sem JS.
- Procure regressão em catálogo compartilhado e carrinho legado.

Exemplo: para addon, demonstre que ID inelegível não passa mesmo se o frontend mostrar checkbox manipulado.

### B — segurança

- GET/POST e parâmetros de URI: a rota permite mutação via GET?
- Cookies/sessão: flags, fixação, regeneração e estado mínimo.
- CSRF: todo POST mutável verifica token da mesma sessão?
- XSS: texto vai por e() ou textContent? Existe HTML cru?
- SQL injection: toda entrada externa é vinculada? Nomes SQL são allowlisted?
- Upload: erro, MIME real, pixels, memória, extensão, nome, execução e traversal.
- IDOR: grupo/ID enviado é resolvido contra conjunto administrativo autorizado?
- Carrinho/localStorage: preço, nome, addon e quantidade são revalidados?
- Arquivos/segredos/logs: path confinado, .env excluído, logs sem senha/hash.
- HTTPS/cache: Secure e no-store nos lugares corretos.

### C — concorrência e integridade

- Que operação precisa ser atômica?
- Banco e filesystem podem divergir em qual janela?
- Sete dias validam antes e atualizam dentro da transação?
- Substituição de imagem bloqueia/associa/limpa na ordem correta?
- Visibilidade sofre lost update ou precisa de versionamento?
- Applicator é realmente idempotente e preserva edição?
- Retry após falha duplica arquivo/metadata?

Desenhe timelines concorrentes. Não chame algo de race condition sem uma interleaving reproduzível.

### D — manutenibilidade

- Responsabilidades da classe são coesas?
- Dependências são explícitas no construtor?
- Contrato array está documentado/testado?
- Há query/transformação duplicada?
- Config e banco têm fronteira clara?
- Mensagens/limites aparecem em mais de um lugar?
- Documentação e deploy acompanharam a feature?
- O teste observa comportamento ou apenas texto do source?

Para cada observação, cite arquivo/método/linha atual, impacto e teste que provaria a melhoria.

## Agenda de investigação do HEAD atual

Estes itens são hipóteses ou lacunas. Use “investigar”, “confirmar” e “avaliar”; não os classifique automaticamente como bugs.

1. **Possível perda da imagem gerenciada ao normalizar carrinho.** app.js::loadCart() aceita image apenas quando começa com /assets/images/, enquanto ProductImageStorage gera /uploads/products/.... Confirmar se um item com imagem gerenciada reaparece no pedido com src vazio após recarregar.
2. **Drift do README.** Trechos ainda dizem que horário efetivo vem de config/business.php, que não há calendário administrativo e que a conexão é somente leitura. Comparar com Operations, visibilidade e imagens antes de usar README como evidência.
3. **Allowlist do mirror versus primeira instalação.** deploy/hostgator/config/deploy.php inclui database/migrations, mas não database/patches nem bin/; confirmar como patches/applicators de visibilidade, imagens e operações chegam/rodam na HostGator.
4. **Caminho do manifesto na documentação.** O README de deploy menciona config/deploy.php; o arquivo real está em deploy/hostgator/config/deploy.php.
5. **Queries repetidas no Admin/Operations.** AdminController::index() monta visibilidade, grupos de imagens e operations em qualquer aba; dashboard() chama notice/schedule mais de uma vez. Medir antes de otimizar.
6. **Resumo de horários heterogêneos.** BusinessHoursService::scheduleHours() usa os períodos do primeiro dia configurado. Confirmar se o texto continua correto quando dias têm horários diferentes.
7. **Rate limit limitado à sessão.** Uma nova sessão pode reiniciar contagem. Avaliar ameaça, proxy/IP e risco de bloquear usuários legítimos antes de propor solução.
8. **Ausência de integração automatizada com MySQL/MariaDB.** A suíte usa fakes e inspeção estática; confirmar DDL, tipos TIME, multi-statement, locks e diferenças da hospedagem em ambiente descartável.
9. **Extensões de upload fora do composer.json.** Fileinfo e GD/WebP são checados em runtime, não requisitos Composer. Avaliar preflight de deploy e documentação do PHP da HostGator.
10. **Compensação arquivo/banco.** Forçar falhas em cada janela e confirmar órfãos possíveis; preservar metadata é deliberadamente conservador.
11. **Comportamento sem JavaScript.** Navegação/leitura funcionam, mas adicionar produto e checkout não têm fallback funcional. Confirmar requisito de produto antes de tratar como defeito.
12. **Feedback de storefront_blocked em página antiga.** O backend bloqueia, mas app.js não trata esse code de forma específica como trata business_closed; avaliar mensagem/estado do botão em aba já aberta.
13. **Observabilidade.** Logger é arquivo diário e /health é mínimo. Avaliar rotação, permissões, correlação, monitoramento e alertas sem expor internos.
14. **Headers de segurança.** Confirmar no Apache/HostGator se CSP, HSTS, nosniff e frame policy são definidos fora do código antes de concluir ausência.
15. **Uploads persistentes no deploy.** Confirmar backup, permissões, anti-execução e preservação durante atualização manual.
16. **Dependência de configuração e banco.** Operations não tem fallback depois do bootstrap: schema ausente impede montar calendário. Confirmar ordem operacional de primeira instalação.
17. **Responsabilidade do AdminController.** Ele concentra visibilidade, imagens, notice e horários. Avaliar divisão somente se complexidade/testes justificarem.
18. **Contratos baseados em arrays.** Catalog, status e cart têm muitos campos sem DTO. Medir custo de erro/refatoração antes de introduzir objetos.
19. **Cache.** StorefrontCatalogService cacheia apenas dentro do request; não há cache HTTP/aplicacional de catálogo. Avaliar carga e invalidação antes de adicionar.
20. **Documentação de produção.** O contexto informa publicação na HostGator, mas o repositório não registra smoke tests datados por funcionalidade. Construir matriz sem inventar validações.

## Critério “eu entendo este projeto”

Conclua somente quando conseguir, sem consultar este documento:

- [ ] desenhar arquitetura e fluxos laterais;
- [ ] explicar front controller, bootstrap e DI manual;
- [ ] listar todas as rotas e respectivos métodos;
- [ ] seguir request até SQL/arquivo/HTML/JSON/WhatsApp;
- [ ] separar banco compartilhado e estruturas da Vitrine;
- [ ] explicar active versus storefront_visible e hierarquia;
- [ ] explicar produto operacional versus público, variantes e aliases;
- [ ] explicar addons, line key e cálculo com quantidade;
- [ ] explicar login, dummy hash, rate limit, sessão e CSRF;
- [ ] explicar pipeline e compensações de upload;
- [ ] explicar saoJorgeCartV2 e limites de confiança;
- [ ] explicar checkout autoritativo e cada erro principal;
- [ ] explicar calendário, próxima abertura e precedência do notice;
- [ ] executar e interpretar testes/lint/check;
- [ ] gerar e auditar o mirror sem publicar;
- [ ] explicar HTTPS, Secure cookie e o caso de CSRF 403;
- [ ] distinguir implementado, automatizado, local e produção;
- [ ] propor mudança pequena com risco, patch mínimo e teste de regressão.

## Artefatos que o estudo deve produzir

1. diagrama de componentes;
2. diagrama das tabelas envolvidas;
3. mapa de rotas;
4. matriz de visibilidade;
5. diagrama produto operacional -> produto público;
6. diagrama de variantes;
7. fluxo de addons;
8. fluxo de autenticação;
9. fluxo de upload;
10. fluxo do carrinho;
11. fluxo do checkout;
12. matriz de ameaças;
13. matriz requisito -> código -> teste -> evidência;
14. checklist HostGator;
15. lista priorizada de achados.

Classifique achados:

| Prioridade | Critério |
|---|---|
| P0 | segurança, perda de dados ou pedido incorreto grave |
| P1 | falha funcional importante ou integridade |
| P2 | borda, observabilidade ou manutenção relevante |
| P3 | melhoria interna, legibilidade ou dívida técnica |

Cada achado precisa de: evidência, impacto, reprodução, correção proposta e teste de regressão. Hipótese sem reprodução continua como investigação.

## Ordem recomendada da documentação de apoio

1. README.md — contexto e comandos, lido criticamente por conter trechos a atualizar.
2. docs/LOCAL_DEVELOPMENT.md — laboratório e portas.
3. docs/ARCHITECTURE.md — convenções e responsabilidades.
4. docs/CATALOG_INTEGRATION.md — dados reais, padrões de nomes e limites.
5. docs/ADMIN.md — sessão, rotas e limites de escrita.
6. docs/STOREFRONT_VISIBILITY.md — publicação independente e sem cascade.
7. docs/PRODUCT_IMAGES.md — schema, storage, processamento e HostGator.
8. docs/STOREFRONT_OPERATIONS.md — notice, calendário e precedência.
9. docs/SECURITY.md — checklist transversal e produção.
10. deploy/hostgator/README.md — build, arquivos protegidos e operação manual.
11. Este plano novamente — consolidar lacunas e artefatos depois de ler o código.

## Revisão final sugerida

Faça uma defesa oral de 45 minutos:

1. desenhe arquitetura e banco em 10 minutos;
2. trace produto -> carrinho -> WhatsApp em 10 minutos;
3. trace login e upload em 10 minutos;
4. explique notice, horário e deploy em 10 minutos;
5. apresente dois achados com evidência e teste em 5 minutos.

Se depender de frases como “o teste passa, então está seguro” ou “a IA fez assim”, volte à ficha por fluxo. O resultado esperado é independência técnica: conseguir confirmar comportamento no código, escolher evidência adequada e propor mudanças pequenas sem ultrapassar a fronteira do sistema operacional compartilhado.
