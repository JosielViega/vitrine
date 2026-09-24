# Destaques da Home

## Persistência e seed

A seleção pública da Home fica na linha canônica **id = 1** da tabela
**storefront_home_highlights**. São persistidos somente os slugs dos conceitos
públicos agrupados:

- **featured_product_slug**, obrigatório;
- **popular_product_1_slug**, opcional;
- **popular_product_2_slug**, opcional.

Quando o repositório completo estiver disponível no ambiente, o applicator
**composer storefront:home-schema** cria a tabela e insere a linha inicial com
**featured** e os dois primeiros itens de **popular** de
**config/storefront.php**. A cláusula idempotente preserva escolhas já editadas
no Admin. Esses valores de arquivo passam a ser apenas defaults de primeira
instalação.

O mirror da HostGator contém somente arquivos de runtime. Ele não inclui
**bin/apply-storefront-home-schema.php** nem
**database/patches/004_add_storefront_home_highlights.sql**, e o builder não
executa migrations ou patches. Portanto, o comando acima não está disponível
dentro do mirror e a atualização do schema de produção deve ser feita em uma
etapa separada e consciente.

## Runtime

**StorefrontHomeHighlightsService** lê a configuração persistida e resolve cada
slug contra o catálogo público a cada request. Nome, imagem, descrição,
variantes e preços vêm sempre do catálogo atual; eles não são copiados para a
tabela.

Se um produto configurado deixar de ser público, ele não aparece na Home e não
é substituído por outro produto. O slug permanece salvo para preservar a
intenção administrativa. O Admin mostra um aviso até que a seleção seja
corrigida ou o produto volte a ser público.

## Admin

**Admin > Home** permite escolher um destaque obrigatório e até dois produtos
em “Mais pedidos”. As opções são agrupadas por **Categoria › Subcategoria** e
vêm somente do catálogo público, o que exclui produtos inativos, ocultos,
bloqueados pelos pais e addons.

Os três slots devem usar produtos diferentes. O segundo popular isolado é
compactado para a primeira posição. O preview usa os dados atuais do catálogo e
marca “Não salvo” enquanto a seleção divergir do valor carregado. O salvamento
usa CSRF e Post/Redirect/Get.

## Ordem de deploy na HostGator

Antes de enviar o novo mirror, aplique o schema separadamente no banco de
produção pelo phpMyAdmin/cPanel:

1. Faça backup do banco e dos arquivos.
2. No phpMyAdmin, selecione explicitamente o banco de produção correto.
3. Execute o conteúdo de
   **database/patches/004_add_storefront_home_highlights.sql**.
4. Execute o seed idempotente abaixo, que usa exatamente os defaults atuais de
   **config/storefront.php**:

```sql
INSERT INTO storefront_home_highlights (
    id,
    featured_product_slug,
    popular_product_1_slug,
    popular_product_2_slug
) VALUES (
    1,
    'camarao-c-batata-e-aipim',
    'carne-c-aipim',
    'batata'
)
ON DUPLICATE KEY UPDATE id = id;
```

5. Confirme a linha sem alterá-la:

```sql
SELECT
    id,
    featured_product_slug,
    popular_product_1_slug,
    popular_product_2_slug,
    updated_at
FROM storefront_home_highlights
WHERE id = 1;
```

6. Somente depois gere, revise e envie o mirror para a HostGator.
7. Abra **Admin > Home**, confirme os três slots e faça um salvamento de teste
   apenas se a configuração exibida estiver correta.
8. Valide a Home pública, inclusive imagens, preços e produtos ocultos.

O seed usa **ON DUPLICATE KEY UPDATE id = id** e nunca sobrescreve uma
configuração já existente. Em outro ambiente que tenha o repositório completo,
é possível executar **composer storefront:home-schema** no lugar dos passos 3 e
4. Esse comando não existe dentro do mirror da HostGator.

O patch ou applicator deve ser executado conscientemente no ambiente correto.
Esta documentação não autoriza alterações no banco de produção.
