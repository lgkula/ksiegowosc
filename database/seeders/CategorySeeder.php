<?php

namespace Database\Seeders;

use App\Enums\Direction;
use App\Enums\RuleField;
use App\Enums\RuleMatch;
use App\Enums\Treatment;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Contractor;
use App\Models\ContractorGroup;
use App\Models\ManualTemplate;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // [nazwa, kierunek, sposób ujęcia, % PIT, % VAT, grupa]
        $categories = [
            ['Paliwo', Direction::Cost, Treatment::Cost, 75, 50, 'Samochód'],
            ['Samochód – eksploatacja', Direction::Cost, Treatment::Cost, 75, 50, 'Samochód'],
            ['Samochód – ubezpieczenie', Direction::Cost, Treatment::Cost, 75, 50, 'Samochód'],
            ['Noclegi', Direction::Cost, Treatment::Cost, 100, 100, 'Podróże'],
            ['Gastronomia', Direction::Cost, Treatment::Cost, 100, 0, 'Podróże'],
            ['Usługi obce', Direction::Cost, Treatment::Cost, 100, 100, 'Usługi'],
            ['Księgowość', Direction::Cost, Treatment::Cost, 100, 100, 'Usługi'],
            ['Telefon i internet', Direction::Cost, Treatment::Cost, 100, 100, 'Biuro'],
            ['Oprogramowanie i IT', Direction::Cost, Treatment::Cost, 100, 100, 'Biuro'],
            ['Biuro i materiały', Direction::Cost, Treatment::Cost, 100, 100, 'Biuro'],
            ['Opłaty bankowe', Direction::Cost, Treatment::Cost, 100, 100, 'Finanse'],
            ['Inne koszty', Direction::Cost, Treatment::Cost, 100, 100, 'Inne'],
            ['ZUS społeczne', Direction::Cost, Treatment::IncomeDeduction, 0, 0, 'Składki'],
            ['Składka zdrowotna', Direction::Cost, Treatment::Health, 0, 0, 'Składki'],
            ['Podatki (PIT, VAT)', Direction::Cost, Treatment::None, 0, 0, 'Podatki'],
            ['Wydatki prywatne / niepodatkowe', Direction::Cost, Treatment::None, 0, 0, 'Inne'],
            ['Sprzedaż usług', Direction::Revenue, Treatment::Revenue, 100, 100, 'Przychody'],
            ['Wypłata promocji', Direction::Revenue, Treatment::Revenue, 100, 100, 'Przychody'],
            ['Inne przychody', Direction::Revenue, Treatment::Revenue, 100, 100, 'Przychody'],
        ];

        $ids = [];
        foreach ($categories as $sort => [$name, $direction, $treatment, $pit, $vat, $group]) {
            $ids[$name] = Category::updateOrCreate(['name' => $name], [
                'direction' => $direction,
                'treatment' => $treatment,
                'pit_percent' => $pit,
                'vat_percent' => $vat,
                'group' => $group,
                'sort' => $sort,
            ])->id;
        }

        // [pole, dopasowanie, wzorzec, kategoria, priorytet]
        $rules = [
            [RuleField::ItemName, RuleMatch::Regex, '\b(pb ?9[58]|on|lpg|benzyna|olej napędowy|diesel|verva|efecta|v-power|ultimate|miles)\b', 'Paliwo', 10],
            [RuleField::ItemName, RuleMatch::Regex, '\b(kawa|hot ?dog|kanapka|napój|woda|baton|zapiekanka|posiłek)\b', 'Gastronomia', 20],
            [RuleField::ItemName, RuleMatch::Regex, '\b(myjnia|mycie|płyn do spryskiwaczy|adblue|olej silnikowy|serwis|wymiana opon|przegląd)\b', 'Samochód – eksploatacja', 15],
            [RuleField::ItemName, RuleMatch::Regex, '\b(nocleg|hotel|zakwaterowanie|pobyt)\b', 'Noclegi', 10],
            [RuleField::ItemName, RuleMatch::Regex, '\b(oc|ac|polisa)\b', 'Samochód – ubezpieczenie', 5],
            [RuleField::ItemName, RuleMatch::Regex, '\b(abonament|internet|telefon)\b', 'Telefon i internet', 5],
            [RuleField::ContractorName, RuleMatch::Regex, '(orlen|\bbp\b|shell|circle k|moya|amic|lotos|avia)', 'Paliwo', 10],
            [RuleField::ContractorName, RuleMatch::Regex, '(hotel|booking|apartament)', 'Noclegi', 10],
            [RuleField::ContractorName, RuleMatch::Contains, 'zakład ubezpieczeń społecznych', 'ZUS społeczne', 10],
        ];

        foreach ($rules as [$field, $match, $pattern, $category, $priority]) {
            CategoryRule::updateOrCreate(
                ['field' => $field, 'pattern' => $pattern],
                ['match' => $match, 'category_id' => $ids[$category], 'priority' => $priority, 'active' => true]
            );
        }

        $groups = [
            'Orlen' => 'orlen',
            'BP' => 'bp europa;bp polska;bp stacja',
            'Shell' => 'shell',
            'Circle K' => 'circle k',
            'Moya' => 'moya;anwim',
            'Amic' => 'amic',
            'Avia' => 'avia',
        ];

        foreach ($groups as $name => $pattern) {
            $group = ContractorGroup::updateOrCreate(['name' => $name], ['pattern' => $pattern]);

            // Szablon paliwowy dla mniejszych sieci, których faktury trafiają do aplikacji ręcznie.
            if (in_array($name, ['Moya', 'Amic', 'Avia'], true)) {
                $contractor = Contractor::firstOrCreate(
                    ['full_name' => $name.' (stacja paliw)'],
                    ['short_name' => $name, 'group_id' => $group->id, 'default_category_id' => $ids['Paliwo']]
                );

                ManualTemplate::updateOrCreate(['name' => 'Paliwo – '.$name], [
                    'direction' => Direction::Cost,
                    'contractor_id' => $contractor->id,
                    'item_name' => 'Olej napędowy ON',
                    'category_id' => $ids['Paliwo'],
                    'vat_rate' => '23',
                    'default_unit' => 'l',
                ]);
            }
        }
    }
}
