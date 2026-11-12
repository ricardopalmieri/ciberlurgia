# Site Ciberlurgia v1: plano de trabalho

Versão de 11/11/2026. Hospedagem: Hostinger compartilhada (HTML + PHP, sem Node no servidor).

## Decisões de arquitetura

- **Sem build obrigatório.** HTML, CSS e JS puros (módulos ES). O three.js fica copiado no site, só os módulos usados. Sobe por FTP ou pelo Git da Hostinger.
- **Cada página existe de verdade em HTML.** Funciona sem JS, é indexada pelo Google e abre em celular fraco. O JS entra por cima: intercepta o clique, faz a transição e troca o conteúdo sem recarregar.
- **2D é a base, 3D é camada.** Um botão 2D/3D fica sempre visível e a escolha é lembrada. Sem WebGL, com `prefers-reduced-motion`, economia de dados ou pouca memória, abre em 2D. Na primeira visita em computador, abre em 3D.
- **PHP só onde precisa:** contagem dos links mais buscados e clicados (arquivo SQLite ou JSON com trava, sem cookie e sem dado pessoal).

## Estrutura

A raiz do repositório é a raiz do site (sobe inteira para `public_html`, menos `_fonte/`, `docs/` e `.claude/`).

```
index.php            roteador único (/, /linkoteca/, /ferramentas/, /coletivo/, /manifesto/, /diario/, /admin/, /api/contagem)
.htaccess            HTTPS, URLs limpas, bloqueio das pastas internas, cache
app/                 PHP (bloqueado na web): nucleo.php, esquemas.php, views/, admin/, api_contagem.php
conteudo/*.json      TODO o conteúdo do site (bloqueado na web; editado pelo painel /admin)
privado/             usuários, sessões, backups, contagem (bloqueado na web)
uploads/             imagens enviadas pelo painel (só webp/jpg/png)
assets/              css, js, fontes, img, vendor/three
identidade/          manual de identidade visual; /identidade/padroes/ é o guia de padrões do site
_fonte/              originais (PNGs, manifesto, guia de links, versão antiga) — não sobe
docs/                planos e instruções — não sobe
```

## Navegação

- **3D:** capa com as gotas de metal (metaballs) e sinapses do manual. Clicar numa gota faz a câmera voar para dentro dela. Lá dentro, um clarão de têmpera faz a transição e abre a página. Na Linkoteca, as categorias viram gotas menores ligadas por sinapses. A lista de links aparece num painel HTML por cima, porque texto em 3D é ruim de ler e de buscar.
- **2D:** mosaico de gotas desenhadas (SVG/CSS). Clicar faz zoom no objeto clicado e um fade para a página seguinte (View Transitions API, com fallback simples em CSS).
- As duas usam as mesmas páginas e os mesmos dados. Só muda a casca.

## Fases

| Fase | Entrega | Depende de |
|---|---|---|
| 0 | Estrutura de pastas, tokens do manual, `.htaccess`, cabeçalho/rodapé, botão 2D/3D | nada |
| 1 | Linkoteca: sistematizar links em categorias e tags, busca (sem acento, tolerante a erro), filtros, destaques | arquivo de links |
| 2 | Ferramentas úteis e Sobre o coletivo (carrossel, manifesto, botão para a identidade) | links Noise Tupi, fotos e bios |
| 3 | Navegação 2D com zoom + fade | fases 1 e 2 |
| 4 | Navegação 3D: voo para dentro das gotas, subgotas na Linkoteca | fase 3 |
| 5 | PHP de contagem ("mais buscados") | fase 1 |
| 6 | Testes: celular simples, Lighthouse, acessibilidade, texto (humanizar), slop-eval; pacote para subir na Hostinger | tudo |

As fases 1, 2 e 4 podem rodar em paralelo com agentes separados depois que a fase 0 fixar o esqueleto.

## Orçamento de peso (por página, primeira visita)

- 2D: até ~150 KB (HTML + CSS + JS + fontes), imagens à parte, com lazy-load.
- 3D: até ~250 KB a mais, carregado só quando o 3D liga.
