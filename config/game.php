<?php

return [
    'initial_date' => '2026-01-01',
    'initial_capital_cents' => 10_000_000,
    'victory' => [
        'days' => 180,
        'equity_cents' => 20_000_000,
    ],
    'income_statement' => [
        'operating_expense_categories' => ['fixed_expense', 'maintenance', 'operating_expense', 'payroll'],
    ],
    'events' => [
        'daily_chance_basis_points' => 2_500,
        'definitions' => [
            'influencer_recommendation' => ['weight' => 25, 'duration_days' => 3, 'demand_factor_basis_points' => 16_000],
            'heavy_rain' => ['weight' => 25, 'duration_days' => 2, 'demand_factor_basis_points' => 8_500],
            'supplier_delay' => ['weight' => 15, 'delay_days' => 2],
            'trending_product' => ['weight' => 20, 'duration_days' => 5, 'demand_factor_basis_points' => 12_000, 'reference_price_factor_basis_points' => 12_000],
            'emergency_maintenance' => ['weight' => 15, 'minimum_cents' => 50_000, 'maximum_cents' => 200_000],
        ],
    ],
    'hr' => [
        'payroll_day' => 10,
        'departments' => ['Administração', 'Comercial', 'Operações', 'Logística'],
        'roles' => ['Assistente', 'Analista', 'Coordenador', 'Gerente', 'Diretor'],
        'salary_matrix_cents' => [
            'Administração' => ['Assistente' => 250_000, 'Analista' => 380_000, 'Coordenador' => 550_000, 'Gerente' => 800_000, 'Diretor' => 1_200_000],
            'Comercial' => ['Assistente' => 270_000, 'Analista' => 420_000, 'Coordenador' => 620_000, 'Gerente' => 900_000, 'Diretor' => 1_350_000],
            'Operações' => ['Assistente' => 260_000, 'Analista' => 400_000, 'Coordenador' => 580_000, 'Gerente' => 850_000, 'Diretor' => 1_280_000],
            'Logística' => ['Assistente' => 240_000, 'Analista' => 360_000, 'Coordenador' => 520_000, 'Gerente' => 780_000, 'Diretor' => 1_150_000],
        ],
    ],
    'sales' => [
        'owner_base_capacity_units' => 5,
        'productivity_min_basis_points' => 8_500,
        'productivity_max_basis_points' => 11_500,
        'commercial_capacity_by_role' => [
            'Assistente' => 8,
            'Analista' => 14,
            'Coordenador' => 20,
            'Gerente' => 26,
            'Diretor' => 34,
        ],
    ],
    'population' => [
        'size' => 100,
        'first_names' => [
            ['Ana', 'Feminino'], ['Beatriz', 'Feminino'], ['Camila', 'Feminino'], ['Carla', 'Feminino'], ['Clara', 'Feminino'],
            ['Daniela', 'Feminino'], ['Elisa', 'Feminino'], ['Gabriela', 'Feminino'], ['Isabela', 'Feminino'], ['Julia', 'Feminino'],
            ['Bruno', 'Masculino'], ['Caio', 'Masculino'], ['Daniel', 'Masculino'], ['Eduardo', 'Masculino'], ['Felipe', 'Masculino'],
            ['Gabriel', 'Masculino'], ['Henrique', 'Masculino'], ['Lucas', 'Masculino'], ['Marcos', 'Masculino'], ['Rafael', 'Masculino'],
        ],
        'last_names' => ['Almeida', 'Barbosa', 'Cardoso', 'Costa', 'Ferreira', 'Gomes', 'Lima', 'Martins', 'Melo', 'Moreira', 'Nascimento', 'Oliveira', 'Pereira', 'Ribeiro', 'Rocha', 'Rodrigues', 'Santos', 'Silva', 'Souza', 'Vieira'],
        'locations' => [
            ['city' => 'São Paulo', 'state' => 'SP'], ['city' => 'Rio de Janeiro', 'state' => 'RJ'],
            ['city' => 'Belo Horizonte', 'state' => 'MG'], ['city' => 'Curitiba', 'state' => 'PR'],
            ['city' => 'Porto Alegre', 'state' => 'RS'], ['city' => 'Florianópolis', 'state' => 'SC'],
            ['city' => 'Salvador', 'state' => 'BA'], ['city' => 'Recife', 'state' => 'PE'],
            ['city' => 'Fortaleza', 'state' => 'CE'], ['city' => 'Brasília', 'state' => 'DF'],
        ],
    ],
    'products' => [
        ['sku' => 'CAF-500', 'name' => 'Café Premium 500g', 'reference_price_cents' => 3_490, 'base_daily_demand' => 12, 'base_cost_cents' => 1_900],
        ['sku' => 'CHA-100', 'name' => 'Chá Especial 100g', 'reference_price_cents' => 2_490, 'base_daily_demand' => 8, 'base_cost_cents' => 1_200],
        ['sku' => 'CHO-070', 'name' => 'Chocolate 70%', 'reference_price_cents' => 1_890, 'base_daily_demand' => 15, 'base_cost_cents' => 950],
        ['sku' => 'GEL-ART', 'name' => 'Geleia Artesanal', 'reference_price_cents' => 2_790, 'base_daily_demand' => 7, 'base_cost_cents' => 1_400],
        ['sku' => 'BIS-AMA', 'name' => 'Biscoito Amanteigado', 'reference_price_cents' => 1_690, 'base_daily_demand' => 18, 'base_cost_cents' => 800],
    ],
    'suppliers' => [
        ['name' => 'Distribuidora Alfa', 'profile' => 'Equilibrado', 'lead_time_days' => 3, 'payment_term_days' => 7, 'reliability_percent' => 95, 'cost_percent' => 100],
        ['name' => 'Atacado Econômico', 'profile' => 'Econômico', 'lead_time_days' => 7, 'payment_term_days' => 0, 'reliability_percent' => 85, 'cost_percent' => 92],
        ['name' => 'Entrega Expressa', 'profile' => 'Rápido', 'lead_time_days' => 1, 'payment_term_days' => 14, 'reliability_percent' => 98, 'cost_percent' => 112],
    ],
    'fixed_expenses' => [
        ['description' => 'Aluguel', 'amount_cents' => 800_000, 'day_of_month' => 5],
        ['description' => 'Estrutura administrativa', 'amount_cents' => 1_200_000, 'day_of_month' => 10],
        ['description' => 'Serviços e utilidades', 'amount_cents' => 250_000, 'day_of_month' => 15],
    ],
];
