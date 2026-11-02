<?php

return [
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'before_or_equal' => 'O campo :attribute deve ser uma data igual ou anterior a :date.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'date' => 'O campo :attribute não é uma data válida.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'exists' => 'O valor selecionado para :attribute é inválido.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => ['numeric' => 'O campo :attribute não pode ser maior que :max.', 'string' => 'O campo :attribute não pode ter mais de :max caracteres.'],
    'min' => ['numeric' => 'O campo :attribute deve ser no mínimo :min.', 'string' => 'O campo :attribute deve ter no mínimo :min caracteres.'],
    'numeric' => 'O campo :attribute deve ser um número.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_with' => 'O campo :attribute é obrigatório quando :values for informado.',
    'size' => ['string' => 'O campo :attribute deve ter :size caracteres.'],
    'unique' => 'Este :attribute já está cadastrado.',

    'attributes' => [
        'nome' => 'nome', 'cpf' => 'CPF', 'cnpj' => 'CNPJ', 'email' => 'e-mail', 'password' => 'senha',
        'matricula' => 'matrícula', 'data_admissao' => 'data de admissão', 'data_demissao' => 'data de desligamento',
        'razao_social' => 'razão social', 'empresa_id' => 'empresa', 'jornada_inicio' => 'início da jornada',
        'acesso_email' => 'e-mail de login', 'acesso_senha' => 'senha', 'motivo' => 'motivo', 'descricao' => 'descrição',
        'entrada' => 'entrada', 'horas' => 'horas', 'competencia' => 'competência', 'data' => 'data', 'hora' => 'hora',
        'dias.*.saida1' => 'saída do intervalo', 'dias.*.saida2' => 'saída', 'tipo' => 'tipo',
    ],
];
