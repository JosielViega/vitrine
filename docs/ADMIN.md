# Admin da Vitrine

O painel em `/admin` controla exclusivamente a publicação do cardápio público. Ele não é um CRUD e não altera nomes, preços, hierarquia, ordenação, imagens nem o estado operacional `active`.

## Autenticação e sessão

- As credenciais são consultadas diretamente em `admin_users` com prepared statement.
- A senha é validada com `password_verify()` e nunca é armazenada, exibida ou registrada em log.
- O painel não cria usuários, não altera hashes e não utiliza `ADMIN_PASSWORD`.
- A sessão e o cookie pertencem à vitrine. Não há compartilhamento de sessão ou SSO com o `lanchonete-app`.
- Não há “Lembrar-me” e `admin_remember_tokens` não é lida nem escrita.
- O ID da sessão é regenerado no login e no logout.
- Cinco falhas dentro de dez minutos bloqueiam temporariamente novas tentativas naquela sessão.

## Rotas

| Método | Rota | Finalidade |
| --- | --- | --- |
| `GET` | `/admin/login` | Formulário de login |
| `POST` | `/admin/login` | Autenticação |
| `GET` | `/admin` | Categorias, subcategorias e produtos |
| `POST` | `/admin/visibility` | Alternância de `storefront_visible` |
| `POST` | `/admin/logout` | Encerramento da sessão administrativa |

Todos os POSTs usam o `App\Core\Csrf` existente. Login e páginas autenticadas enviam `Cache-Control: no-store, no-cache, must-revalidate`.

## Limites de escrita

O repositório administrativo possui três comandos explícitos e independentes:

```sql
UPDATE categories SET storefront_visible = :visible WHERE id = :id;
UPDATE subcategories SET storefront_visible = :visible WHERE id = :id;
UPDATE products SET storefront_visible = :visible WHERE id = :id;
```

`active` aparece no painel somente como informação. Ocultar uma categoria ou subcategoria não modifica seus filhos; o painel mostra separadamente o controle direto e o estado efetivo resultante da hierarquia.
