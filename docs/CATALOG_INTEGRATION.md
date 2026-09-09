# Integração do catálogo real

Relatório da inspeção somente leitura realizada em 9 de setembro de 2026 contra a cópia local do banco `lanchonete`, disponível em `127.0.0.1:3310`. Credenciais e DSN completo não são registrados neste documento.

## Resumo

- 4 categorias, todas ativas: **Bebidas**, **Cigarro**, **Comidas** e **Outros**.
- 17 subcategorias, todas ativas.
- 137 produtos no total: 135 ativos e 2 inativos.
- 96 produtos `regular` (94 ativos e 2 inativos).
- 41 produtos `kitchen`, todos ativos.
- Nenhum preço negativo ou igual a zero.
- Nenhum nome ativo duplicado dentro da mesma subcategoria.

## Subcategorias

- Bebidas: Geral, Cervejas, Refrigerantes, Sucos e Doses.
- Cigarro: Geral, Maço e Palito.
- Comidas: Geral, Chips, Doces, Salgados, Tira Gosto, Espetinho, Porções e Acréscimo.
- Outros: Geral.

## Exemplos reais

Entre os nomes encontrados estão: Água Mineral, Brahma Latão, Coca-Cola 2 Litros, Suco de Maracujá, Coxinha, Espetinho Carne, Batata, Camarão c/ Batata e Aipim, Moda da Casa, Dunhill e Casco.

## Padrões contendo “Meia”

Foram encontrados 20 produtos ativos, todos `kitchen`, todos na subcategoria **Porções** e todos no formato observado `Meia: <nome base>`:

1. Meia: Batata
2. Meia: Camarão
3. Meia: Camarão c/ Aipim
4. Meia: Camarão c/ Batata
5. Meia: Camarão c/ Batata e Aipim
6. Meia: Carne c/ Aipim
7. Meia: Carne c/ Batata
8. Meia: Carne c/ Batata e Aipim
9. Meia: Frango c/ Aipim
10. Meia: Frango c/ Batata
11. Meia: Frango c/ Batata e Aipim
12. Meia: Mista
13. Meia: Mista c/ Aipim
14. Meia: Mista c/ Batata e Aipim
15. Meia: Mista c/Batata
16. Meia: Pescada c/ Aipim
17. Meia: Pescada c/ Batata
18. Meia: Pescada c/ Batata e Aipim
19. Meia: Pescadinha
20. Meia: Porção Carne

A regra pública reconhece `Meia: <nome base>` sem diferenciar maiúsculas de minúsculas e com espaços flexíveis ao redor e depois de `:`. O pareamento exige igualdade do nome normalizado e a mesma subcategoria; não há aproximação por similaridade. No catálogo real, 19 pares são diretos e a exceção explícita `Porção Carne` → `Porção de carne`, declarada em `config/storefront.php`, completa 20 agrupamentos. Assim, os 20 cards separados de meia porção deixam de existir e o catálogo público passa de 135 registros ativos para 115 produtos públicos.

Cada produto agrupado mantém as variantes na ordem **Inteira** e **Meia**, com `product_id`, preço inteiro em centavos e `kind` vindos do MySQL. Exemplos conferidos: Camarão c/ Batata e Aipim (79/8500 e 82/7200), Batata (59/3000 e 116/2000), Pescadinha (112/4200 e 113/3200) e Porção de carne (127/4200 e 130/3200).

Uma meia porção sem produto-base continua publicada isoladamente com a variante `Meia`; um produto inteiro sem meia continua isolado; nomes iguais em subcategorias diferentes não são agrupados. O formato legado `<nome base> - Meia` permanece compatível.

## Inconsistências relevantes

- `Meia: Porção Carne` difere do inteiro `Porção de carne`; essa única equivalência é intencional, auditável e configurada sem IDs fixos.
- Todas as categorias e subcategorias possuem `sort_order = 0`; a ordem secundária acaba sendo alfabética.
- Há um registro inativo `Água Mineral` e um registro ativo `Agua Mineral`, com diferença apenas de acentuação.
- Foi encontrado o nome `Peps 2 Litros`, possivelmente uma grafia incompleta de marca; ele é publicado sem correção porque o banco é a fonte oficial do nome.
- Nomes como Latão, Litrinho, marcas e sabores permanecem produtos independentes.

## Limites e segurança

A vitrine executa exclusivamente consultas `SELECT`. O repository público exige produto, subcategoria e categoria ativos por meio de `INNER JOIN` explícito. IDs, nomes, preços em centavos, categoria, subcategoria, status e `kind` vêm do MySQL. Imagens, descrições, destaque, populares e relacionados ficam em `config/storefront.php`.

O carrinho continua no navegador e guarda o ID MySQL da variante. Seus preços não devem ser considerados confiáveis por uma futura API; qualquer integração posterior deverá recarregar e validar preço e disponibilidade no servidor.
