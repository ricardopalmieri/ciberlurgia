# Ciberlurgia

Site do coletivo de cultura digital Ciberlurgia, de Santo André, no ABC Paulista. *Por uma cultura digital livre e soberana.*

- **Linkoteca:** endereços para tirar certidão, achar edital, falar com os conselhos e acompanhar o dinheiro público da cultura no Grande ABC.
- **Ferramentas:** ferramentas livres que rodam no navegador.
- **Coletivo:** quem está na bancada, o manifesto e a identidade visual.
- **Diário:** notícias da oficina.

A navegação é um mapa: em 3D (three.js) para quem tem aparelho e conexão, e em 2D leve para todo mundo. Sem JavaScript, o site continua funcionando como páginas comuns.

## Como funciona

PHP 8.1+, sem banco de dados e sem build. Roda em hospedagem compartilhada comum (Apache ou LiteSpeed), na raiz do domínio ou numa subpasta.

```
index.php        roteador único
.htaccess        HTTPS, URLs limpas, bloqueio das pastas internas
app/             PHP: núcleo, esquemas, páginas, painel /admin
conteudo/*.json  todo o conteúdo do site (editável pelo painel)
assets/          css, js, fontes, imagens, three.js
identidade/      manual de identidade visual e guia de padrões (/identidade/padroes/)
privado/         usuários, sessões, backups (fica só no servidor)
uploads/         imagens enviadas pelo painel (fica só no servidor)
_fonte/          originais: marca, manifesto, guia de links
docs/            planos e instruções do painel
```

## Rodar na sua máquina

```bash
php -S localhost:8080 index.php
```

Precisa das extensões `mbstring`, `gd` e `fileinfo`. Para criar o primeiro usuário do painel, veja [docs/admin.md](docs/admin.md).

## Licença

Conteúdo em CC BY-SA 4.0. A licença do código ainda vai ser escolhida pelo coletivo. As fontes têm licenças próprias (SIL OFL e LGPL), anotadas em `identidade/marca/fontes/`.
