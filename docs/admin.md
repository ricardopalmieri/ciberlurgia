# Painel admin da Ciberlurgia

Endereço: `https://SEU-SITE/admin`. Só entra quem tem usuário e senha.

## Instalar o primeiro usuário (Hostinger)

O painel só se instala uma vez, e só se você provar que tem acesso ao servidor.

1. Suba o site inteiro para `public_html` (a pasta `privado/` junto).
2. No Gerenciador de Arquivos do hPanel, entre em `privado/` e crie um arquivo chamado `INSTALAR` (sem extensão).
3. Dentro dele, cole um código longo e aleatório (mínimo 16 caracteres; use 30 ou mais). Exemplo para gerar: um gerador de senhas do gerenciador de senhas. Salve.
4. Abra `https://SEU-SITE/admin/instalar`. Digite o código, escolha o nome de usuário (3 a 32 caracteres: minúsculas, números, ponto, traço) e uma senha de **no mínimo 12 caracteres**.
5. Ao terminar, o painel apaga o `INSTALAR` sozinho. Confira no Gerenciador que ele sumiu; se ainda estiver lá, apague à mão.
6. Entre em `https://SEU-SITE/admin/entrar`.

Regras da instalação:
- Se já existe `privado/usuarios.php`, a página `/admin/instalar` responde "não encontrado".
- Sem o arquivo `privado/INSTALAR`, também não instala. Para recomeçar do zero: apague `privado/usuarios.php` e crie um novo `INSTALAR`.
- Esqueceu a senha de todo mundo? Mesma coisa: apague `privado/usuarios.php`, crie o `INSTALAR` e instale de novo (o conteúdo do site não é tocado).

Antes de instalar, ative o SSL gratuito no hPanel. Com HTTPS o cookie de login vai marcado como `Secure`.

## Como usar

**Painel.** Cartões para cada parte do site (textos gerais, páginas, categorias, Linkoteca, ferramentas, pessoas, diário). Mais embaixo, os termos mais buscados e os links mais abertos da Linkoteca (só leitura).

**Editor.** Em cada coleção:
- Lista à esquerda com busca. Botões para adicionar, duplicar, excluir e mover para cima/baixo (a ordem da lista é a ordem no site).
- Formulário à direita. O identificador (slug) é sugerido a partir do título enquanto você não mexe nele; "Gerar do título" refaz.
- Texto em markdown: `## título`, `### subtítulo`, `- lista`, `> citação`, `**negrito**`, `*itálico*`, `[texto](endereço)`. O botão **Prévia** mostra como fica.
- Imagem: **Enviar imagem** ou **Escolher da galeria**.
- **Nada vale até clicar em Salvar** (ou Ctrl+S). Se algo estiver errado, o painel não salva nada e marca os campos com problema. Se você tentar sair com alterações não salvas, o navegador avisa.
- Não dá para remover ou renomear uma categoria que ainda tem links usando ela.

**Versões antigas.** O botão guarda as últimas versões de cada coleção (o site guarda até 30). Restaurar uma versão guarda a atual como backup antes, então dá para voltar atrás.

**Galeria** (`/admin/galeria`). Imagens enviadas. Aceita JPG, PNG e WebP até 5 MB e 8000 px por lado. O servidor converte tudo para WebP (largura máxima 1600 px, qualidade 82), o que também remove metadados (como GPS da foto). Só dá para excluir imagem que nenhum conteúdo usa.

**Usuários** (`/admin/usuarios`). Trocar a própria senha (pede a atual), adicionar e remover usuários. Não dá para remover a si mesmo nem o último usuário. Embaixo, a lista das últimas entradas no painel (certas e erradas), com aparelho e rede.

**Mensagens** (`/admin/mensagens`). O que chega pelo formulário de `/contato/`. Cada mensagem vai para o e-mail de contato e também fica guardada aqui (as 300 mais recentes), com botão para responder e para apagar. Se aparecer "não chegou por e-mail", o servidor não conseguiu mandar: a mensagem está salva mesmo assim.

## Segurança, em resumo

- Senhas com hash (Argon2id quando o servidor tem; senão o padrão do PHP). Mensagem de erro de login é sempre a mesma.
- 5 tentativas erradas bloqueiam o IP e o usuário por 15 minutos (`privado/tentativas.json`).
- Sessão de 30 minutos sem uso, no máximo 8 horas, presa ao navegador. Cookie `HttpOnly`, `SameSite=Strict`, só em `/admin` (dentro da pasta do site, se for subpasta).
- Todo envio (POST) exige token CSRF e origem igual ao próprio site.
- `privado/`, `app/` e `conteudo/` têm `.htaccess` com `Require all denied`. `uploads/` só serve imagem e não executa script.
- O painel fala `noindex` e nunca é guardado em cache.
- Senha nova precisa de 12+ caracteres, não pode ser repetitiva, nem ter o nome de usuário ou palavras óbvias (ciberlurgia, cccd, senha, admin, 123456…).
- Campo-isca no login: robô que preenche tudo é recusado mesmo com a senha certa.
- Registro de entradas em `privado/acessos.json` (IP guardado só pela metade, ex.: `189.12.x.x`). Entrada em aparelho ou rede nova manda um aviso por e-mail. Se ninguém reconhecer, troque a senha: isso derruba as outras sessões.

## Formulário de contato e antispam

`/contato/` manda a mensagem para o e-mail oficial pelo `mail()` do servidor, com remetente do próprio domínio e "responder para" a pessoa que escreveu. Sem captcha de terceiros (nada de Google, funciona sem JavaScript):

- campo-isca escondido; ficha assinada com a hora: envio em menos de 4 s, ficha reaproveitada ou com mais de 2 h é recusada;
- 3 mensagens a cada 10 min e 8 por dia por IP; teto de 60 por dia no site;
- mais de 3 links, códigos `[url]`/HTML ou link no nome são recusados.

Robô pego pela isca ou pela ficha recebe "enviado" de mentira e nada é gravado.

## Endereço oficial e e-mail

O código já vem com `https://ciberlurgia.org` (usado no `canonical`, na imagem de compartilhamento e no `sitemap.xml`) e `contato@ciberlurgia.org`. O e-mail que aparece no rodapé é o do painel (Textos gerais → E-mail de contato); vazio, vale o oficial. Para trocar sem mexer no código, use `privado/config.php`:

```php
<?php return [
    'url' => 'https://ciberlurgia.org',
    'email_contato' => 'contato@ciberlurgia.org',     // recebe o formulário
    'email_remetente' => 'contato@ciberlurgia.org',   // "From" dos e-mails (precisa ser do domínio)
    'email_alertas' => 'contato@ciberlurgia.org',     // avisos de entrada no painel ('' desliga)
];
```

Na Hostinger, a caixa `contato@ciberlurgia.org` precisa existir (hPanel → E-mails) para o `mail()` entregar bem. Depois de publicar, mande uma mensagem de teste pelo formulário.

## Arquivos

- `app/admin/`: o código do painel (roteador, segurança, páginas, API).
- `assets/admin/admin.js` e `admin.css`: a interface.
- `app/contato.php`: formulário de contato e antispam.
- `privado/usuarios.php`, `privado/sessoes/`, `privado/tentativas.json`, `privado/acessos.json`, `privado/mensagens/`, `privado/backups/`: dados do painel. Faça backup de `privado/` e de `conteudo/` e `uploads/` de vez em quando.

## Rodar em subpasta ou na raiz

O site funciona em qualquer pasta, sem configurar nada: `https://dominio.com/` ou `https://dominio.com/pontodigital/cccd/`. Para mudar de lugar, é só copiar todos os arquivos (incluindo `.htaccess`) para a nova pasta.

- **Como a base é descoberta** (`app/nucleo.php`, constante `BASE`: vazia na raiz, `/pontodigital/cccd` numa subpasta): 1) `privado/config.php`, se existir; 2) a pasta do site comparada com a pasta pública do servidor (`DOCUMENT_ROOT`); 3) a pasta do `SCRIPT_NAME`.
- **O que muda e o que não muda.** Os JSON de `conteudo/` e o painel continuam guardando caminhos começando com `/` (`/uploads/x.webp`, `/manifesto/`), sem a base. Só o que sai para o navegador (links, imagens, redirects, cookies, JS) ganha a base, pela função `u()`.
- **Override manual**, caso a detecção erre (alguns servidores com a pasta pública mapeada de um jeito incomum): crie `privado/config.php` com
  ```php
  <?php return ['base' => '/pontodigital/cccd'];   // na raiz: ['base' => '']
  ```
  `privado/` não é servido pela web.
- **`.htaccess`**: todas as regras são relativas à própria pasta. Só se der 404 em tudo ou loop de redirecionamento, descomente a linha `RewriteBase` (comentada) e ponha a pasta do site.
- Ao mudar de pasta, quem estava logado no painel precisa entrar de novo (o cookie vale só para `/pasta/admin`).
