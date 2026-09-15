# Funcionamento da Vitrine

A área **Admin > Funcionamento** controla exclusivamente a Vitrine pública. Ela não altera o `lanchonete-app` nem as tabelas operacionais de categorias, subcategorias, produtos ou usuários administrativos.

## Persistência e instalação

O patch `database/patches/003_add_storefront_operations.sql` cria:

- `storefront_settings`: linha canônica `id = 1`, contendo o estado e os textos do quadro de aviso;
- `storefront_business_hours`: sete linhas, usando weekdays ISO-8601 (`1` para segunda até `7` para domingo).

Em cada ambiente, execute uma vez (repetições são seguras):

```bash
composer storefront:operations-schema
```

O applicator é idempotente. Registros inexistentes recebem os valores iniciais de `config/business.php`; configurações já editadas nunca são sobrescritas. O timezone permanece `America/Sao_Paulo`.

## Regras

O aviso é texto puro e sempre escapado na renderização. Quando ativo, o servidor responde às rotas `/`, `/cardapio`, `/produto/{slug}` e `/pedido` apenas com a tela do comunicado, sem navegação, carrinho ou ações de compra e com `Cache-Control: no-store, no-cache, must-revalidate`. As rotas `/admin`, `/admin/*`, `/admin/login`, `/health` e os assets continuam livres.

O checkout aplica a precedência:

1. aviso ativo: HTTP 409, `storefront_blocked`;
2. aviso desativado e estabelecimento fechado: HTTP 409, `business_closed`;
3. aviso desativado e estabelecimento aberto: fluxo normal.

O calendário semanal é salvo em uma única transação. Dias abertos exigem horários `HH:MM` e `open < close`; períodos que atravessam meia-noite não são aceitos. O calendário persistido alimenta o `BusinessHoursService`, que continua responsável por status atual e próxima abertura.

## Ordem futura de produção (HostGator)

1. Fazer backup do banco.
2. Aplicar o schema de operações da Vitrine.
3. Conferir o seed inicial.
4. Publicar o código.
5. Entrar em **Admin > Funcionamento**.
6. Confirmar que o aviso está desligado.
7. Confirmar os sete dias e horários.
8. Executar smoke tests da Vitrine, checkout, Admin e `/health`.

O schema de produção não deve ser aplicado sem autorização explícita.
