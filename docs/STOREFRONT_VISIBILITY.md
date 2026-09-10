# Visibilidade do catálogo público

## Dois estados independentes

`active` continua pertencendo ao sistema operacional da lanchonete. Ele indica se o registro está operacionalmente ativo e seu significado não deve ser alterado pela vitrine.

`storefront_visible` autoriza a publicação do registro na vitrine. A coluna existe em `categories`, `subcategories` e `products` como `TINYINT(1) NOT NULL DEFAULT 1`.

Um produto público exige os dois estados habilitados nos três níveis: produto, subcategoria e categoria. Ocultar um nível pai não altera os valores dos filhos. Assim, reexibir a categoria ou subcategoria restaura naturalmente os descendentes que mantiveram `storefront_visible = 1`.

O `DEFAULT 1` mantém todos os registros existentes visíveis após a aplicação e torna compatíveis os novos registros criados pelo sistema original, mesmo quando seus `INSERT`s ignoram a coluna.

## Variantes Inteira e Meia

Cada linha de produto mantém sua própria visibilidade. Inteira e Meia podem ser publicadas ou ocultadas de forma independente, sem alterar IDs, nomes, aliases, preços ou o agrupamento atual. Se as duas linhas forem ocultadas, o produto agrupado deixa de existir no catálogo público.

O checkout resolve `productId` usando esse mesmo catálogo filtrado. Uma variante ocultada depois de entrar no carrinho passa a ser tratada pelo comportamento existente de item indisponível (`cart_invalid`), sem regra paralela no checkout.

## Aplicação segura

Confira primeiro se o `.env` aponta para o banco pretendido. Em seguida execute:

```bash
composer storefront:visibility-schema
```

O comando específico consulta `information_schema.COLUMNS`, adiciona somente colunas ausentes e valida tipo, nulabilidade e valor padrão. Ele pode ser executado novamente e não cria tabela de migrations ou qualquer tabela auxiliar. Não use `composer migrate` para este patch no banco compartilhado.

O SQL de referência está em `database/patches/001_add_storefront_visibility.sql`. Prefira o comando acima porque ele é idempotente.

## Verificação

Sem expor credenciais, consulte:

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('categories', 'subcategories', 'products')
  AND COLUMN_NAME = 'storefront_visible';

SELECT storefront_visible, COUNT(*) FROM categories GROUP BY storefront_visible;
SELECT storefront_visible, COUNT(*) FROM subcategories GROUP BY storefront_visible;
SELECT storefront_visible, COUNT(*) FROM products GROUP BY storefront_visible;
```

## Administração futura

Uma etapa futura adicionará um `/admin` próprio da vitrine para listar categorias, subcategorias e produtos e alternar exclusivamente `storefront_visible`. Esta fundação não implementa painel, login, usuário ou senha. A autenticação futura poderá usar `admin_users` sem modificar essa tabela agora.

## Rollback manual

O rollback é deliberadamente manual e destrutivo. Ele só pode ser considerado depois que a aplicação deixar de consultar as colunas:

```sql
ALTER TABLE products DROP COLUMN storefront_visible;
ALTER TABLE subcategories DROP COLUMN storefront_visible;
ALTER TABLE categories DROP COLUMN storefront_visible;
```

Não execute esse rollback enquanto esta versão da vitrine estiver ativa. Faça backup e valide consumidores do banco compartilhado antes de remover as colunas.
