<?php
/* Esquema de cada arquivo em conteudo/. O painel monta os formulários a partir daqui
   e o núcleo valida tudo contra ele antes de gravar.
   Tipos: texto, textarea, markdown, slug, url, email, imagem, data, tags, booleano, numero,
          selecao (opcoes), referencia (colecao + campo), links (lista de rótulo + url). */

$cores = ['forja', 'brasa', 'fusao', 'arco', 'cereja'];

return [
    'site' => [
        'rotulo' => 'Textos gerais', 'tipo' => 'objeto',
        'campos' => [
            ['nome' => 'nome', 'rotulo' => 'Nome do coletivo', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 60],
            ['nome' => 'tagline', 'rotulo' => 'Frase curta', 'tipo' => 'texto', 'max' => 120],
            ['nome' => 'descricao', 'rotulo' => 'Descrição para buscadores', 'tipo' => 'textarea', 'max' => 300],
            ['nome' => 'chamada', 'rotulo' => 'Chamada da capa', 'tipo' => 'textarea', 'max' => 400],
            ['nome' => 'email', 'rotulo' => 'E-mail de contato', 'tipo' => 'email'],
            ['nome' => 'redes', 'rotulo' => 'Redes e contatos', 'tipo' => 'links'],
            ['nome' => 'rodape', 'rotulo' => 'Texto do rodapé', 'tipo' => 'textarea', 'max' => 400],
        ],
    ],
    'paginas' => [
        'rotulo' => 'Páginas de texto', 'tipo' => 'lista', 'chave' => 'slug', 'titulo_item' => 'titulo',
        'campos' => [
            ['nome' => 'slug', 'rotulo' => 'Identificador', 'tipo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'titulo', 'rotulo' => 'Título', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 120],
            ['nome' => 'subtitulo', 'rotulo' => 'Subtítulo', 'tipo' => 'texto', 'max' => 200],
            ['nome' => 'corpo', 'rotulo' => 'Texto', 'tipo' => 'markdown'],
            ['nome' => 'imagem', 'rotulo' => 'Imagem', 'tipo' => 'imagem'],
        ],
    ],
    'categorias' => [
        'rotulo' => 'Categorias da Linkoteca', 'tipo' => 'lista', 'chave' => 'slug', 'titulo_item' => 'nome',
        'campos' => [
            ['nome' => 'slug', 'rotulo' => 'Identificador', 'tipo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 60],
            ['nome' => 'descricao', 'rotulo' => 'Descrição', 'tipo' => 'textarea', 'max' => 400],
            ['nome' => 'cor', 'rotulo' => 'Cor da gota', 'tipo' => 'selecao', 'opcoes' => $cores],
        ],
    ],
    'links' => [
        'rotulo' => 'Linkoteca', 'tipo' => 'lista', 'chave' => 'id', 'titulo_item' => 'titulo',
        'campos' => [
            ['nome' => 'id', 'rotulo' => 'Identificador', 'tipo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'titulo', 'rotulo' => 'Título', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 140],
            ['nome' => 'url', 'rotulo' => 'Endereço', 'tipo' => 'url', 'obrigatorio' => true, 'max' => 500],
            ['nome' => 'descricao', 'rotulo' => 'Para que serve', 'tipo' => 'textarea', 'max' => 400],
            ['nome' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'referencia', 'colecao' => 'categorias', 'campo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'cidade', 'rotulo' => 'Abrangência', 'tipo' => 'selecao', 'opcoes' => CIDADES],
            ['nome' => 'tags', 'rotulo' => 'Palavras-chave', 'tipo' => 'tags'],
            ['nome' => 'destaque', 'rotulo' => 'Destaque na capa da Linkoteca', 'tipo' => 'booleano'],
            ['nome' => 'verificado', 'rotulo' => 'Última verificação', 'tipo' => 'data'],
        ],
    ],
    'ferramentas' => [
        'rotulo' => 'Ferramentas úteis', 'tipo' => 'lista', 'chave' => 'id', 'titulo_item' => 'titulo',
        'campos' => [
            ['nome' => 'id', 'rotulo' => 'Identificador', 'tipo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'titulo', 'rotulo' => 'Nome', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 80],
            ['nome' => 'url', 'rotulo' => 'Endereço', 'tipo' => 'url', 'obrigatorio' => true, 'max' => 500],
            ['nome' => 'descricao', 'rotulo' => 'Para que serve', 'tipo' => 'textarea', 'max' => 400],
            ['nome' => 'imagem', 'rotulo' => 'Imagem', 'tipo' => 'imagem'],
            ['nome' => 'autoria', 'rotulo' => 'Quem fez', 'tipo' => 'texto', 'max' => 80],
            ['nome' => 'tags', 'rotulo' => 'Palavras-chave', 'tipo' => 'tags'],
        ],
    ],
    'pessoas' => [
        'rotulo' => 'Pessoas do coletivo', 'tipo' => 'lista', 'chave' => 'id', 'titulo_item' => 'nome', 'max_itens' => 60,
        'campos' => [
            ['nome' => 'id', 'rotulo' => 'Identificador', 'tipo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 80],
            ['nome' => 'funcao', 'rotulo' => 'Na bancada', 'tipo' => 'texto', 'max' => 120],
            ['nome' => 'bio', 'rotulo' => 'Mini bio', 'tipo' => 'textarea', 'max' => 600],
            ['nome' => 'foto', 'rotulo' => 'Foto', 'tipo' => 'imagem'],
            ['nome' => 'contatos', 'rotulo' => 'Contatos', 'tipo' => 'links'],
        ],
    ],
    'diario' => [
        'rotulo' => 'Diário (posts)', 'tipo' => 'lista', 'chave' => 'slug', 'titulo_item' => 'titulo',
        'campos' => [
            ['nome' => 'slug', 'rotulo' => 'Identificador', 'tipo' => 'slug', 'obrigatorio' => true],
            ['nome' => 'titulo', 'rotulo' => 'Título', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 140],
            ['nome' => 'data', 'rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true],
            ['nome' => 'resumo', 'rotulo' => 'Resumo', 'tipo' => 'textarea', 'max' => 400],
            ['nome' => 'corpo', 'rotulo' => 'Texto', 'tipo' => 'markdown'],
            ['nome' => 'imagem', 'rotulo' => 'Imagem', 'tipo' => 'imagem'],
            ['nome' => 'tags', 'rotulo' => 'Palavras-chave', 'tipo' => 'tags'],
            ['nome' => 'publicado', 'rotulo' => 'Publicado', 'tipo' => 'booleano'],
        ],
    ],
];
