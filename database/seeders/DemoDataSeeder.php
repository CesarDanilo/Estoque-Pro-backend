<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Person;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /** Guarda os CPFs/CNPJs já usados nesta execução (documento é único globalmente) */
    private array $usedDocuments = [];

    public function run(): void
    {
        $user = $this->criarUsuarioDemo();

        $this->command?->info('Criando grupos...');
        $groups = $this->criarGrupos($user);

        $this->command?->info('Criando fornecedores...');
        $suppliers = $this->criarFornecedores($user);

        $this->command?->info('Criando pessoas...');
        $people = $this->criarPessoas($user);

        $this->command?->info('Criando produtos...');
        $products = $this->criarProdutos($user, $groups, $suppliers);

        $this->command?->info('Criando vendas...');
        $this->criarVendas($user, $people, $products);

        $this->command?->info('Seeder finalizado com sucesso!');
        $this->command?->line('Login demo -> email: admin@estoquepro.com | senha: password');
    }

    // =========================================================================
    // USUÁRIO
    // =========================================================================
    private function criarUsuarioDemo(): User
    {
        return User::updateOrCreate(
            ['email' => 'admin@estoquepro.com'],
            [
                'name' => 'Administrador Estoque Pro',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }

    // =========================================================================
    // GRUPOS (categorias de produto)
    // =========================================================================
    private function criarGrupos(User $user): array
    {
        $nomes = [
            'Bebidas', 'Alimentos', 'Limpeza', 'Higiene Pessoal', 'Papelaria',
            'Ferramentas', 'Eletrônicos', 'Vestuário', 'Calçados', 'Brinquedos',
            'Automotivo', 'Pet Shop', 'Jardinagem', 'Informática', 'Móveis',
            'Utilidades Domésticas', 'Padaria', 'Frios e Laticínios', 'Congelados', 'Bazar',
        ];

        $grupos = [];
        foreach ($nomes as $nome) {
            $grupos[$nome] = Group::create([
                'user_id' => $user->id,
                'name' => $nome,
                'description' => "Produtos da categoria {$nome}.",
                'active' => true,
            ]);
        }

        return $grupos;
    }

// =========================================================================
    // FORNECEDORES (25) — agora vivem em people, com category = supplier
    // =========================================================================
    private function criarFornecedores(User $user): array
    {
        $faker = fake('pt_BR');
        $estados = ['SP', 'RJ', 'MG', 'PR', 'RS', 'SC', 'MS', 'GO', 'BA', 'PE'];
        $fornecedores = [];

        for ($i = 1; $i <= 25; $i++) {
            $documento = $this->gerarCnpj();
            $ativo = $faker->boolean(85); // 85% ativos

            $fornecedores[] = Person::create([
                'user_id' => $user->id,
                'category' => 'supplier',
                'type' => 'company', // fornecedor é sempre pessoa jurídica
                'name' => $faker->unique()->company(),
                'trade_name' => $faker->boolean(60) ? $faker->companySuffix() . ' ' . $faker->word() : null,
                'document' => $documento,
                'state_registration' => (string) $faker->numberBetween(100000000, 999999999),
                'email' => $faker->unique()->companyEmail(),
                'phone' => $this->gerarTelefone(),
                'contact_person' => $faker->name(),
                'zip_code' => $this->gerarCep(),
                'street' => $faker->streetName(),
                'number' => (string) $faker->numberBetween(10, 9999),
                'complement' => $faker->boolean(30) ? 'Galpão ' . $faker->numberBetween(1, 20) : null,
                'neighborhood' => $faker->city() . ' - Bairro Industrial',
                'city' => $faker->city(),
                'state' => $faker->randomElement($estados),
                'active' => $ativo,
                'notes' => $faker->boolean(20) ? 'Entrega preferencial pela manhã.' : null,
            ]);
        }

        return $fornecedores;
    }

    // =========================================================================
    // PESSOAS (50 — clientes físicos e jurídicos, category = client)
    // =========================================================================
    private function criarPessoas(User $user): array
    {
        $faker = fake('pt_BR');
        $pessoas = [];

        for ($i = 1; $i <= 50; $i++) {
            $ehJuridica = $faker->boolean(30); // 30% pessoa jurídica

            if ($ehJuridica) {
                $pessoas[] = Person::create([
                    'user_id' => $user->id,
                    'category' => 'client',
                    'type' => 'company',
                    'name' => $faker->unique()->company(),
                    'document' => $this->gerarCnpj(),
                    'gender' => null,
                    'birth_date' => null,
                    'phone' => $this->gerarTelefone(),
                    'email' => $faker->unique()->companyEmail(),
                    'zip_code' => $this->gerarCep(),
                    'city' => $faker->city(),
                    'address' => $faker->streetAddress(),
                    'active' => $faker->boolean(90),
                ]);
            } else {
                $genero = $faker->randomElement(['male', 'female', 'other']);
                $nome = $genero === 'female' ? $faker->firstNameFemale() : $faker->firstNameMale();
                $nome .= ' ' . $faker->lastName();

                $pessoas[] = Person::create([
                    'user_id' => $user->id,
                    'category' => 'client',
                    'type' => 'individual',
                    'name' => $nome,
                    'document' => $this->gerarCpf(),
                    'gender' => $genero,
                    'birth_date' => $faker->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                    'phone' => $this->gerarTelefone(),
                    'email' => $faker->unique()->safeEmail(),
                    'zip_code' => $this->gerarCep(),
                    'city' => $faker->city(),
                    'address' => $faker->streetAddress(),
                    'active' => $faker->boolean(90),
                ]);
            }
        }

        return $pessoas;
    }

    // =========================================================================
    // PRODUTOS (~50)
    // =========================================================================
    private function criarProdutos(User $user, array $groups, array $suppliers): array
    {
        // [nome, grupo, custo, preço de venda]
        $catalogo = [
            ['Coca-Cola 2L', 'Bebidas', 6.50, 9.90],
            ['Suco de Laranja 1L', 'Bebidas', 4.20, 7.50],
            ['Cerveja Pilsen 350ml', 'Bebidas', 2.10, 3.90],
            ['Água Mineral 500ml', 'Bebidas', 1.10, 2.50],
            ['Arroz Tipo 1 5kg', 'Alimentos', 18.00, 26.90],
            ['Feijão Carioca 1kg', 'Alimentos', 5.80, 8.90],
            ['Macarrão Espaguete 500g', 'Alimentos', 2.90, 4.80],
            ['Óleo de Soja 900ml', 'Alimentos', 5.10, 7.50],
            ['Açúcar Refinado 1kg', 'Alimentos', 3.40, 5.20],
            ['Detergente Neutro 500ml', 'Limpeza', 2.30, 4.50],
            ['Água Sanitária 1L', 'Limpeza', 2.80, 4.90],
            ['Sabão em Pó 1kg', 'Limpeza', 8.10, 12.90],
            ['Desinfetante 1L', 'Limpeza', 4.50, 7.90],
            ['Sabonete em Barra 90g', 'Higiene Pessoal', 1.20, 2.30],
            ['Shampoo Anticaspa 350ml', 'Higiene Pessoal', 9.80, 16.90],
            ['Pasta de Dente 90g', 'Higiene Pessoal', 3.10, 5.50],
            ['Papel Higiênico 12un', 'Higiene Pessoal', 14.00, 21.90],
            ['Caderno Universitário 96 folhas', 'Papelaria', 8.90, 14.90],
            ['Caneta Esferográfica Azul', 'Papelaria', 0.60, 1.50],
            ['Papel Sulfite A4 500fls', 'Papelaria', 18.50, 27.90],
            ['Furadeira de Impacto 500W', 'Ferramentas', 89.00, 149.90],
            ['Jogo de Chaves de Fenda', 'Ferramentas', 22.00, 39.90],
            ['Trena 5m', 'Ferramentas', 9.50, 17.90],
            ['Fone de Ouvido Bluetooth', 'Eletrônicos', 45.00, 89.90],
            ['Carregador USB-C 20W', 'Eletrônicos', 18.00, 34.90],
            ['Caixa de Som Portátil', 'Eletrônicos', 60.00, 119.90],
            ['Camiseta Básica Algodão', 'Vestuário', 15.00, 29.90],
            ['Calça Jeans Masculina', 'Vestuário', 45.00, 89.90],
            ['Jaqueta Corta-Vento', 'Vestuário', 55.00, 109.90],
            ['Tênis Esportivo Unissex', 'Calçados', 70.00, 139.90],
            ['Chinelo de Dedo', 'Calçados', 8.00, 17.90],
            ['Sandália Feminina', 'Calçados', 25.00, 49.90],
            ['Boneca de Pano', 'Brinquedos', 20.00, 39.90],
            ['Carrinho de Controle Remoto', 'Brinquedos', 48.00, 89.90],
            ['Quebra-Cabeça 500 peças', 'Brinquedos', 15.00, 29.90],
            ['Óleo Lubrificante 1L', 'Automotivo', 22.00, 34.90],
            ['Kit Palheta Limpador', 'Automotivo', 30.00, 54.90],
            ['Aromatizante Automotivo', 'Automotivo', 5.00, 12.90],
            ['Ração para Cães 15kg', 'Pet Shop', 85.00, 139.90],
            ['Ração para Gatos 3kg', 'Pet Shop', 24.00, 39.90],
            ['Areia Higiênica 4kg', 'Pet Shop', 14.00, 24.90],
            ['Adubo Orgânico 5kg', 'Jardinagem', 9.00, 17.90],
            ['Vaso de Cerâmica Médio', 'Jardinagem', 12.00, 24.90],
            ['Mangueira de Jardim 15m', 'Jardinagem', 28.00, 49.90],
            ['Mouse Óptico USB', 'Informática', 14.00, 27.90],
            ['Teclado Multimídia', 'Informática', 28.00, 54.90],
            ['Pen Drive 32GB', 'Informática', 16.00, 29.90],
            ['Cadeira de Escritório', 'Móveis', 180.00, 329.90],
            ['Mesa de Centro', 'Móveis', 150.00, 279.90],
            ['Estante de Livros 5 Prateleiras', 'Móveis', 210.00, 389.90],
            ['Jogo de Panelas Antiaderente', 'Utilidades Domésticas', 65.00, 119.90],
            ['Conjunto de Talheres 24pçs', 'Utilidades Domésticas', 35.00, 64.90],
            ['Escorredor de Louça', 'Utilidades Domésticas', 20.00, 37.90],
            ['Pão Francês (kg)', 'Padaria', 6.00, 12.90],
            ['Pão de Forma Integral', 'Padaria', 4.50, 8.90],
            ['Bolo de Fubá', 'Padaria', 8.00, 15.90],
            ['Queijo Mussarela (kg)', 'Frios e Laticínios', 28.00, 42.90],
            ['Presunto Fatiado 200g', 'Frios e Laticínios', 7.00, 12.90],
            ['Leite Integral 1L', 'Frios e Laticínios', 3.80, 5.90],
            ['Pizza Congelada Calabresa', 'Congelados', 9.00, 16.90],
            ['Lasanha Congelada 600g', 'Congelados', 10.00, 18.90],
            ['Polpa de Fruta 100g', 'Congelados', 2.00, 3.90],
            ['Vela Aromática', 'Bazar', 6.00, 14.90],
            ['Porta-Retrato', 'Bazar', 8.00, 17.90],
            ['Jogo Americano', 'Bazar', 12.00, 24.90],
        ];

        // Garante no máximo 50 registros, mesmo que o catálogo acima tenha mais
        $catalogo = array_slice($catalogo, 0, 50);

        $faker = fake('pt_BR');
        $abreviacoes = [
            'Bebidas' => 'BEB', 'Alimentos' => 'ALI', 'Limpeza' => 'LIM', 'Higiene Pessoal' => 'HIG',
            'Papelaria' => 'PAP', 'Ferramentas' => 'FER', 'Eletrônicos' => 'ELE', 'Vestuário' => 'VES',
            'Calçados' => 'CAL', 'Brinquedos' => 'BRI', 'Automotivo' => 'AUT', 'Pet Shop' => 'PET',
            'Jardinagem' => 'JAR', 'Informática' => 'INF', 'Móveis' => 'MOV',
            'Utilidades Domésticas' => 'UTI', 'Padaria' => 'PAD', 'Frios e Laticínios' => 'FRI',
            'Congelados' => 'CON', 'Bazar' => 'BAZ',
        ];

        $catalogo = array_slice($catalogo, 0, 50);

        $faker = fake('pt_BR');

        $produtos = [];

        foreach ($catalogo as $index => [$nome, $nomeGrupo, $custo, $preco]) {
            $grupo = $groups[$nomeGrupo];

            $minimo = $faker->numberBetween(5, 15);
            $sorteio = $faker->numberBetween(1, 100);
            $estoque = match (true) {
                $sorteio <= 10 => 0,
                $sorteio <= 30 => $faker->numberBetween(1, $minimo),
                default => $faker->numberBetween($minimo + 5, $minimo + 60),
            };

            $fornecedor = $faker->boolean(75) ? $faker->randomElement($suppliers) : null;

            $produtos[] = Product::create([
                'user_id' => $user->id,
                'group_id' => $grupo->id,
                'supplier_id' => $fornecedor?->id,
                'name' => $nome,
                // 🔴 AQUI: removido 'sku' => $sku
                'cost_price' => $custo,
                'sale_price' => $preco,
                'stock_quantity' => $estoque,
                'min_stock_quantity' => $minimo,
                'description' => null,
                'active' => $faker->boolean(92),
            ]);
        }

        return $produtos;
    }

    // =========================================================================
    // VENDAS (50, com itens)
    // =========================================================================
    private function criarVendas(User $user, array $people, array $products): void
    {
        $faker = fake('pt_BR');
        $formasPagamento = ['Pix', 'Dinheiro', 'Cartão de crédito', 'Cartão de débito'];
        // O enum da tabela `sales` só aceita estes três status:
        $statusPossiveis = ['completed', 'completed', 'completed', 'pending', 'pending', 'cancelled'];

        for ($i = 1; $i <= 50; $i++) {
            $codigo = 'V-' . (1000 + $i);

            $temCliente = $faker->boolean(70);
            $pessoa = $temCliente ? $faker->randomElement($people) : null;

            $qtdItens = $faker->numberBetween(1, 4);
            $produtosDaVenda = $faker->randomElements($products, $qtdItens);

            $subtotal = 0;
            $itensParaCriar = [];

            foreach ($produtosDaVenda as $produto) {
                $quantidade = $faker->numberBetween(1, 5);
                $precoUnitario = (float) $produto->sale_price;
                $totalItem = round($quantidade * $precoUnitario, 2);
                $subtotal += $totalItem;

                $itensParaCriar[] = [
                    'product' => $produto,
                    'quantity' => $quantidade,
                    'unit_price' => $precoUnitario,
                    'total_price' => $totalItem,
                ];
            }
            $subtotal = round($subtotal, 2);

            // Desconto: 30% das vendas têm desconto percentual
            $descontoPercentual = 0;
            $descontoValor = 0;
            if ($faker->boolean(30)) {
                $descontoPercentual = $faker->randomElement([5, 10, 15]);
                $descontoValor = round($subtotal * ($descontoPercentual / 100), 2);
            }

            // Acréscimo: 10% das vendas têm taxa/acréscimo (ex.: entrega)
            $acrescimoValor = 0;
            if ($faker->boolean(10)) {
                $acrescimoValor = $faker->randomFloat(2, 5, 20);
            }

            $total = max(0, round($subtotal - $descontoValor + $acrescimoValor, 2));

            $status = $faker->randomElement($statusPossiveis);

            // Data da venda: espalhada nos últimos 45 dias (últimas 5 sempre "recentes")
            $dataVenda = $i > 45
                ? now()->subDays(50 - $i)->setTime($faker->numberBetween(8, 20), $faker->numberBetween(0, 59))
                : now()->subDays($faker->numberBetween(1, 45))->setTime($faker->numberBetween(8, 20), $faker->numberBetween(0, 59));

            $venda = Sale::create([
                'code' => $codigo,
                'person_id' => $pessoa?->id,
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'discount_value' => $descontoValor,
                'discount_percentage' => $descontoPercentual,
                'surcharge_value' => $acrescimoValor,
                'surcharge_percentage' => 0,
                'total' => $total,
                'payment_method' => $faker->randomElement($formasPagamento),
                'status' => $status,
                'notes' => $faker->boolean(15) ? 'Cliente solicitou nota fiscal.' : null,
            ]);

            foreach ($itensParaCriar as $item) {
                SaleItem::create([
                    'sale_id' => $venda->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'product_sku' => $item['product']->sku,
                    'quantity' => $item['quantity'],
                    'unit_cost_price' => $item['product']->cost_price,
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
            }

            // Ajusta created_at/updated_at sem disparar os eventos automáticos do Eloquent
            Sale::query()->where('id', $venda->id)->update([
                'created_at' => $dataVenda,
                'updated_at' => $dataVenda,
            ]);
        }
    }

    // =========================================================================
    // HELPERS: geração de documentos, telefone e CEP válidos no formato do app
    // (apenas dígitos, sem máscara — igual ao que o front-end envia)
    // =========================================================================
    private function randomDigits(int $quantidade): string
    {
        $digitos = '';
        for ($i = 0; $i < $quantidade; $i++) {
            $digitos .= random_int(0, 9);
        }
        return $digitos;
    }

    private function gerarCpf(): string
    {
        do {
            $base = $this->randomDigits(9);

            $soma = 0;
            for ($i = 0; $i < 9; $i++) $soma += (int) $base[$i] * (10 - $i);
            $resto = ($soma * 10) % 11;
            $d1 = $resto === 10 ? 0 : $resto;

            $soma = 0;
            $base9 = $base . $d1;
            for ($i = 0; $i < 10; $i++) $soma += (int) $base9[$i] * (11 - $i);
            $resto = ($soma * 10) % 11;
            $d2 = $resto === 10 ? 0 : $resto;

            $cpf = $base . $d1 . $d2;
        } while (isset($this->usedDocuments[$cpf]));

        $this->usedDocuments[$cpf] = true;
        return $cpf;
    }

    private function gerarCnpj(): string
    {
        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        do {
            $base = $this->randomDigits(8) . '0001'; // 12 dígitos (8 aleatórios + filial 0001)

            $soma = 0;
            for ($i = 0; $i < 12; $i++) $soma += (int) $base[$i] * $pesos1[$i];
            $resto = $soma % 11;
            $d1 = $resto < 2 ? 0 : 11 - $resto;

            $base13 = $base . $d1;
            $soma = 0;
            for ($i = 0; $i < 13; $i++) $soma += (int) $base13[$i] * $pesos2[$i];
            $resto = $soma % 11;
            $d2 = $resto < 2 ? 0 : 11 - $resto;

            $cnpj = $base . $d1 . $d2;
        } while (isset($this->usedDocuments[$cnpj]));

        $this->usedDocuments[$cnpj] = true;
        return $cnpj;
    }

    private function gerarTelefone(): string
    {
        $ddds = ['11', '21', '31', '41', '51', '61', '67', '71', '81', '85'];
        $ddd = $ddds[array_rand($ddds)];
        // celular: DDD + 9 + 8 dígitos = 11 dígitos no total
        return $ddd . '9' . $this->randomDigits(8);
    }

    private function gerarCep(): string
    {
        // 8 dígitos, sem traço (mesmo formato salvo pelo front-end)
        return $this->randomDigits(5) . $this->randomDigits(3);
    }
}