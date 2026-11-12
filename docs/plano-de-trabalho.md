# Ciberlurgia: plano de trabalho

Coletivo de cultura digital, Santo André, ABC Paulista. Versão 0.3, de 09/11/2026.

Nesta etapa, o objetivo é colocar o coletivo na rede. A ordem é: identidade visual, depois o manual de comunicação e usabilidade, depois o hotsite. O hotsite vai funcionar como um mapa navegável que ganha áreas novas aos poucos.

Uma regra vale para todas as fases: **tem que funcionar em celular simples, com pouca banda.** Cada decisão de design e de código passa por esse teste.

---

## Fase 0: fundação (em andamento)

| Entrega | Situação | Onde está |
|---|---|---|
| Manifesto | Pronto | Google Docs e cópia em `_fonte/manifesto_html/` |
| Selo bordado | Pronto, extraído do documento | `marca/ciberlurgia-selo-*.webp` |
| Skills de apoio (arte, UX, three.js, desempenho, acessibilidade, humanização de texto e design) | Instaladas | `.claude/skills/` (lista em `.claude/skills/FONTES.md`) |
| **Manual de identidade visual v0.3** | Rascunho para o coletivo validar | `manual/manual-identidade-visual.html` |
| Tokens de design | Rascunho | `marca/tokens.css` |
| Fontes recortadas para web e impressão | Prontas | `marca/fontes/` |

**O que mudou na v0.3:** a engrenagem saiu. A marca agora são dois selos bordados: o grande e a miniatura CCCD, sempre estáticos e pousados sobre gotas de metal paradas. Por cima das gotas correm sinapses de luz, que nunca passam sobre o selo. A gota muda de forma de peça para peça e o selo fica igual: é a identidade dinâmica. A fonte larga saiu e entraram a Jost (geométrica, inspirada na Futura) e a Osifont (normógrafo) para cotas e dados. O bordado do selo virou textura digital. No mapa 3D, as áreas abertas viraram gotas com veias de luz, a grade ficou sem borda e ondula devagar, e há pulsos de luz nas soldas.

**O coletivo precisa validar:**
- A paleta de fogo e o azul do arco.
- O uso da miniatura CCCD em tamanhos pequenos (ícone, avatar, carimbo) e o sistema selo sobre gota.
- As fontes: estêncil (Big Shoulders Stencil), Jost, Osifont e Atkinson Hyperlegible. Alternativa à Jost, se preferirem algo no estilo da Bahnschrift: Barlow.
- A licença dos materiais. A proposta é CC BY-SA 4.0 para conteúdo e uma licença livre, ainda a escolher, para o código.

## Fase 1: manual de comunicação e usabilidade

Um documento só, explicando como o coletivo fala e como o site se comporta.

- **Voz e tom:** a bancada como referência. Frase direta, "a gente", verbo de ofício. Glossário com as palavras do coletivo.
- **Usabilidade:** navegação por toque e clique, alvo de toque de no mínimo 44 px, leitura em tela pequena, texto alternativo, contraste, teclado e leitor de tela.
- **Régua de peso:** primeira tela útil com até 200 KB, sem contar o 3D.
- **Regras do 3D:** quando carrega, quando não carrega e o que aparece no lugar.
- **Acessibilidade:** WCAG 2.2 nível AA como piso.
- **Modelos de peça:** post, cartaz de oficina, convite e chamada aberta.

## Fase 2: arquitetura do hotsite

- **Mapa de áreas (nós da rede).** Proposta inicial: Manifesto, Oficinas, Bancadas parceiras (pontos de cultura, escolas, bibliotecas), Acervo aberto, Agenda e Entre na bancada. Cada nó abre quando tiver conteúdo. Até lá aparece como "em forja".
- **Duas camadas com o mesmo conteúdo:**
  1. *Modo leve*, o padrão garantido: HTML e CSS puros, mapa 2D em SVG, funciona até sem JavaScript.
  2. *Modo oficina*, a camada extra: cena three.js com gotas quentes, arame frio, soldas com pulsos e grade sem borda, carregada só se o aparelho e a conexão aguentarem.
- **Decisões técnicas em aberto:** gerador de site estático (Astro ou Vite puro), hospedagem livre (Codeberg Pages ou GitHub Pages), domínio e repositório público.

## Fase 3: protótipo do hotsite

- Cena 3D do mapa com quatro a seis nós, toque, clique e foco por teclado.
- O mesmo mapa em 2D.
- Uma página de verdade para cada nó aberto, começando por Manifesto e Oficinas.

## Fase 4: teste em campo

- Teste em Android de entrada e em 3G simulado.
- Teste com leitor de tela (TalkBack e NVDA).
- Uma oficina de teste com gente do território. O site vai para a bancada, e o que sair da escuta muda o plano.

## Fase 5: abertura gradual

Cada nova ação do coletivo vira um nó no mapa, ligado aos que já existem.

---

## Decisões pendentes do coletivo

- [ ] Aprovar paleta, fontes, miniatura CCCD e o sistema selo sobre gota.
- [ ] Escolher a licença do conteúdo e do código.
- [ ] Registrar o domínio (ciberlurgia.org ou .org.br, por exemplo).
- [ ] Definir quem dá a validação final de cada fase.
- [ ] Fechar a lista inicial de nós do mapa e quais abrem primeiro.
