# Imagens dos produtos da vitrine

## Modelo

Cada produto público possui no máximo uma imagem principal. Não existe galeria, ordenação, legenda ou imagem secundária. Quando uma foto for trocada, as associações passam a apontar para a nova imagem; o registro e o arquivo anteriores podem ser removidos quando não tiverem mais vínculos.

Um produto público pode agrupar mais de uma linha real de `products`. Por isso, Inteira e Meia compartilham o mesmo registro em `storefront_product_images`, com uma associação por `product_id` em `storefront_product_image_products`. A chave primária de `product_id` limita cada linha real a uma imagem, enquanto uma única imagem pode ser vinculada às duas variantes.

As tabelas pertencem exclusivamente à vitrine. Nenhuma coluna ou chave estrangeira foi adicionada a `products`, `categories` ou `subcategories`, mantendo o banco operacional e o lanchonete-app desacoplados.

O schema é aplicado separadamente com:

```bash
composer storefront:images-schema
```

O comando usa a configuração local da aplicação, cria somente as duas tabelas ausentes e valida colunas, índices, engine e chave estrangeira quando elas já existem. Ele não usa nem cria a tabela `migrations`. Em produção, o patch deve ser revisado e aplicado deliberadamente; ele não faz parte do deploy automático.

## Resolução no catálogo

A consulta pública usa `LEFT JOIN`, portanto produtos sem cadastro de imagem continuam disponíveis. A precedência é:

1. imagem administrada nas novas tabelas;
2. imagem editorial em `config/storefront.php`;
3. fallback da categoria;
4. fallback geral.

No agrupamento Inteira/Meia, o catálogo prefere deterministicamente a imagem da linha base/Inteira e usa a imagem da Meia se a primeira estiver ausente. Assim, uma variante isolada conserva sua imagem e uma inconsistência não interrompe a leitura. A consulta nunca corrige associações automaticamente.

## Armazenamento e segurança

Os arquivos futuros ficam em `public/uploads/products/YYYY/MM/`, com nome físico criptograficamente aleatório, por exemplo `/uploads/products/2026/09/4f0d7c28c09e....webp`. O banco guarda somente esse caminho web controlado, nunca um caminho físico do Windows, Linux ou da hospedagem.

`ProductImageStorage` aceita apenas caminhos nesse formato e extensões JPEG, PNG ou WebP, rejeitando travessia de diretório e caminhos arbitrários. Arquivos enviados não são versionados. Todo `public/uploads/` permanece fora do mirror da HostGator, para que uma atualização não copie arquivos locais, apague uploads remotos ou sobrescreva dados persistentes.

As regras em `deploy/hostgator/server-config-examples/uploads-security.rules.example` desabilitam listagem, CGI e execução de scripts. Elas devem ser mescladas manualmente ao `.htaccess` já existente em `public/uploads/` na HostGator; o deploy nunca substitui automaticamente a configuração do servidor.

## Requisitos da próxima etapa

O futuro fluxo HTTP deverá:

- aceitar JPEG, PNG e WebP, validando MIME e conteúdo real da imagem;
- limitar o arquivo original a 8 MB;
- redimensionar para no máximo 1600 px no maior lado;
- preferencialmente re-encodar como WebP com qualidade aproximada de 82;
- remover todos os metadados na re-encode, inclusive EXIF e eventual localização GPS;
- gravar primeiro de forma segura e atualizar arquivo, metadados e associações de maneira coordenada.

Esse processamento exigirá GD com suporte a JPEG, PNG e WebP (ou alternativa equivalente confirmada na hospedagem). A ausência de GD não impede a leitura atual da vitrine; somente bloqueará a futura etapa de processamento de uploads até a extensão ser habilitada.

## Administração e processamento

A aba `Admin > Imagens` trabalha com o mesmo agrupador usado pelo catálogo público. O identificador enviado pelo formulário aponta apenas para o grupo administrativo; os `product_id` reais são sempre recalculados no servidor. Upload e remoção são rotas `POST`, exigem autenticação administrativa e CSRF válido.

O upload aceita JPEG, PNG e WebP, mas não confia em nome, extensão ou Content-Type do navegador. O backend confirma `UPLOAD_ERR_OK`, mede o arquivo temporário (máximo de 8 MB), detecta o MIME real com Fileinfo, valida a imagem com `getimagesize()` e rejeita dimensões inválidas ou mais de 40.000.000 pixels. A imagem é decodificada por GD, orientada pelo EXIF quando a extensão estiver disponível, reduzida proporcionalmente para até 1600 px no maior lado e re-encodada como WebP qualidade 82. Imagens menores não são ampliadas e transparência é preservada quando possível. Como o original nunca é copiado, metadados como EXIF/GPS não chegam ao arquivo final.

A escrita usa um arquivo temporário controlado no diretório final e `rename` antes da transação de associação. Em falha do banco, o novo arquivo é removido e a imagem anterior permanece. Na troca ou remoção, a imagem antiga só é apagada depois do commit e apenas quando não houver nenhum vínculo restante.

### Checklist da HostGator antes de habilitar uploads

- GD habilitado com JPEG, PNG e WebP;
- Fileinfo habilitado;
- EXIF recomendado para corrigir orientação de JPEGs de celular;
- `upload_max_filesize >= 10M` (mínimo funcional de 8 MB);
- `post_max_size >= 12M` e sempre maior que o limite do arquivo;
- `memory_limit` dimensionado para decodificação segura (recomendado pelo menos 256M);
- permissão de escrita mínima compatível em `public/uploads/products` (diretórios 0775, sem 0777);
- regras anti-execução de `deploy/hostgator/server-config-examples/uploads-security.rules.example` mescladas manualmente no servidor;
- confirmar que `public/uploads/` permanece fora do mirror de atualização.

Verificação local em 11/09/2026: GD está habilitado no PHP CLI com suporte a JPEG, PNG e WebP. A DLL de Fileinfo existe, mas não está habilitada no `php.ini`; os testes de codec foram executados habilitando-a somente no processo. EXIF não está disponível. Os limites observados foram `upload_max_filesize=2M`, `post_max_size=8M` e `memory_limit=128M`, portanto a interface alerta que os dois primeiros precisam ser ampliados para oferecer uploads de até 8 MB. Nenhum `php.ini` foi alterado ou versionado.
