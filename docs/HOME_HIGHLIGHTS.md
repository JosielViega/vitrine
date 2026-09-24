# Destaques da Home

## Persistência e seed

A seleção pública da Home fica na linha canônica **id = 1** da tabela
**storefront_home_highlights**. São persistidos somente os slugs dos conceitos
públicos agrupados:

- **featured_product_slug**, obrigatório;
- **popular_product_1_slug**, opcional;
- **popular_product_2_slug**, opcional.

O applicator **composer storefront:home-schema** cria a tabela e insere a linha
inicial com **featured** e os dois primeiros itens de **popular** de
**config/storefront.php**. A cláusula idempotente preserva escolhas já editadas
no Admin. Esses valores de arquivo passam a ser apenas defaults de primeira
instalação.

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

## Ordem de deploy

1. Faça backup do banco e dos arquivos.
2. Disponibilize e execute **composer storefront:home-schema** no ambiente.
3. Confirme a linha **id = 1** e os slugs sem alterar escolhas existentes.
4. Somente depois publique o código que usa a nova tabela.
5. Abra **Admin > Home** e confira os três slots.
6. Valide a Home pública, inclusive imagens, preços e produtos ocultos.

O applicator deve ser executado conscientemente no ambiente correto. Esta
documentação não autoriza alterações no banco de produção.
