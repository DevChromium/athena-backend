<?php

namespace App\Http\Libraries;

use App\Http\Enums\MajorIdentifications;
use App\Http\Traits\Singleton;

class ItemManager
{

    use Singleton;

    private \Illuminate\Support\Collection $prismarineJSData;

    public function __construct()
    {
        $itemDB = \Storage::get('items.json');
        $this->prismarineJSData = collect(json_decode($itemDB))->keyBy('id');
    }


    public static function convertItem(array $item): object
    {
        $input = collect($item);

        $result = $itemInfo = $requirements = $damageTypes = $defenseTypes = $statuses = [];

        $isFixed = static function ($raw) use ($input) {
            if ($input->get('identified') === true) {
                return true;
            }

            return match ($raw) {
                'rawStrength', 'rawDexterity', 'rawIntelligence', 'rawDefence', 'rawAgility' => true,
                default => false,
            };
        };

        $result['displayName'] = $input->get('displayName', str_replace('֎', '', $input['name']));
        $result['tier'] = strtoupper($input['tier']);
        $result['identified'] = $input->get('identified', false);
        $result['powderAmount'] = $input['sockets'];
        $result['attackSpeed'] = $input->get('attackSpeed');

        $result['itemInfo'] = &$itemInfo;
        $itemInfo['type'] = strtoupper($input->get('type', $input->get('accessoryType')));
        $itemInfo['set'] = $input['set'];
        $itemInfo['dropType'] = strtoupper($input['dropType']);
        $itemInfo['armorColor'] = $input->get('armorColor') === '160,101,64' ? null : $input->get('armorColor');

        $result['requirements'] = &$requirements;
        $requirements['quest'] = $input->get('quest');
        $requirements['classType'] = !empty($input->get('classRequirement')) ? strtoupper($input->get('classRequirement')) : null;
        $requirements['level'] = $input->get('level');
        $requirements['strength'] = $input->get('strength');
        $requirements['dexterity'] = $input->get('dexterity');
        $requirements['intelligence'] = $input->get('intelligence');
        $requirements['defense'] = $input->get('defense');
        $requirements['agility'] = $input->get('agility');

        $result['damageTypes'] = &$damageTypes;
        $damageTypes['neutral'] = ignoreZero($input->get('damage'));
        $damageTypes['earth'] = ignoreZero($input->get('earthDamage'));
        $damageTypes['thunder'] = ignoreZero($input->get('thunderDamage'));
        $damageTypes['water'] = ignoreZero($input->get('waterDamage'));
        $damageTypes['fire'] = ignoreZero($input->get('fireDamage'));
        $damageTypes['air'] = ignoreZero($input->get('airDamage'));

        $result['defenseTypes'] = &$defenseTypes;
        $defenseTypes['health'] = ignoreZero($input->get('health'));
        $defenseTypes['earth'] = ignoreZero($input->get('earthDefense'));
        $defenseTypes['thunder'] = ignoreZero($input->get('thunderDefense'));
        $defenseTypes['water'] = ignoreZero($input->get('waterDefense'));
        $defenseTypes['fire'] = ignoreZero($input->get('fireDefense'));
        $defenseTypes['air'] = ignoreZero($input->get('airDefense'));

        $result['statuses'] = &$statuses;

        $result['majorIds'] = $input->get('majorIds');
        $result['restriction'] = $input->get('restrictions');
        $result['lore'] = $input->get('addedLore');

        foreach ($item as $key => $value) {

            if ($key === 'armorType') {
                $itemInfo['material'] = str_replace('chain', 'chainmail', strtolower("minecraft:{$value}_{$itemInfo["type"]}"));
                continue;
            }

            if ($key === 'material' && $value !== null) {
                $itemInfo['material'] = $value;
                $newMaterial = self::instance()->convertMaterial($value);
                $itemInfo['name'] = $newMaterial['name'];
                $itemInfo['damage'] = $newMaterial['damage'];
                continue;
            }

            if (!is_numeric($value) || $value === 0) {
                continue;
            }

            $translatedName = self::translateStatusName($key);
            if ($translatedName === null) {
                continue;
            }
            $status = &$statuses[$translatedName];
            $status['type'] = getStatusType($translatedName);
            $status['isFixed'] = $isFixed($translatedName);
            $status['baseValue'] = $value;
        }

        $itemInfo = cleanNull($itemInfo);
        $requirements = cleanNull($requirements);
        $damageTypes = cleanNull($damageTypes);
        $defenseTypes = cleanNull($defenseTypes);
        $statuses = cleanNull($statuses);
        return cleanNull($result);
    }


    public static function getIdentificationorder(): array
    {
        $result = [];

        $result['order'] = collect([
            'rawStrength',
            'rawDexterity',
            'rawIntelligence',
            'rawDefence',
            'rawAgility',
            //second group {attack stuff}
            'attackSpeed',
            'rawMainAttackDamage',
            'mainAttackDamage',
            'rawSpellDamage',
            'rawSpellDamage',
            'spellDamage',
            'rawThunderSpellDamage',
            'rawFireSpellDamage',
            'rawAirSpellDamage',
            'rawEarthSpellDamage',
            'rawWaterSpellDamage',
            //third group {health/mana stuff}
            'rawHealth',
            'rawHealthRegen',
            'healthRegen',
            'lifeSteal',
            'manaRegen',
            'manaSteal',
            //fourth group {damage stuff}
            'earthDamage',
            'thunderDamage',
            'waterDamage',
            'fireDamage',
            'airDamage',
            //fifth group {defence stuff}
            'earthDefence',
            'thunderDefence',
            'waterDefence',
            'fireDefence',
            'airDefence',
            //sixth group {passive damage}
            'exploding',
            'poison',
            'thorns',
            'reflection',
            //seventh group {movement stuff}
            'walkSpeed',
            'sprint',
            'sprintRegen',
            'rawJumpHeight',
            //eigth group {XP/Gathering stuff}
            'soulPointRegen',
            'lootBonus',
            'lootQuality',
            'stealing',
            'xpBonus',
            'gatherXpBonus',
            'gatherSpeed',
            //ninth group {spell stuff}
            'raw1stSpellCost',
            '1stSpellCost',
            'raw2ndSpellCost',
            '2ndSpellCost',
            'raw3rdSpellCost',
            '3rdSpellCost',
            'raw4thSpellCost',
            '4thSpellCost',
        ])->mapWithKeys(
            fn($value, $key) => [$value => $key + 1]
        )->toArray();

        $groups = &$result['groups'];

        $groups[] = '1-5';
        $groups[] = '6-11';
        $groups[] = '12-17';
        $groups[] = '18-22';
        $groups[] = '23-27';
        $groups[] = '28-31';
        $groups[] = '32-35';
        $groups[] = '36-42';
        $groups[] = '43-50';

        $inverted = &$result['inverted'];

        $inverted[] = '1stSpellCost';
        $inverted[] = '2ndSpellCost';
        $inverted[] = '3rdSpellCost';
        $inverted[] = '4thSpellCost';
        $inverted[] = 'raw1stSpellCost';
        $inverted[] = 'raw2ndSpellCost';
        $inverted[] = 'raw3rdSpellCost';
        $inverted[] = 'raw4thSpellCost';


        return $result;
    }

    public static function getInternalIdentifications(): array
    {
        $result = [];

        $result['STRENGTHPOINTS'] = 'rawStrength';
        $result['DEXTERITYPOINTS'] = 'rawDexterity';
        $result['INTELLIGENCEPOINTS'] = 'rawIntelligence';
        $result['DEFENSEPOINTS'] = 'rawDefence';
        $result['AGILITYPOINTS'] = 'rawAgility';
        $result['DAMAGEBONUS'] = 'mainAttackDamage';
        $result['DAMAGEBONUSRAW'] = 'rawMainAttackDamage';
        $result['SPELLDAMAGE'] = 'spellDamage';
        $result['SPELLDAMAGERAW'] = 'rawSpellDamage';
        $result['HEALTHREGEN'] = 'healthRegen';
        $result['HEALTHREGENRAW'] = 'rawHealthRegen';
        $result['HEALTHBONUS'] = 'rawHealth';
        $result['POISON'] = 'poison';
        $result['LIFESTEAL'] = 'lifeSteal';
        $result['MANAREGEN'] = 'manaRegen';
        $result['MANASTEAL'] = 'manaSteal';
        $result['SPELL_COST_PCT_1'] = '1stSpellCost';
        $result['SPELL_COST_RAW_1'] = 'raw1stSpellCost';
        $result['SPELL_COST_PCT_2'] = '2ndSpellCost';
        $result['SPELL_COST_RAW_2'] = 'raw2ndSpellCost';
        $result['SPELL_COST_PCT_3'] = '3rdSpellCost';
        $result['SPELL_COST_RAW_3'] = 'raw3rdSpellCost';
        $result['SPELL_COST_PCT_4'] = '4thSpellCost';
        $result['SPELL_COST_RAW_4'] = 'raw4thSpellCost';
        $result['THORNS'] = 'thorns';
        $result['REFLECTION'] = 'reflection';
        $result['ATTACKSPEED'] = 'attackSpeed';
        $result['SPEED'] = 'walkSpeed';
        $result['EXPLODING'] = 'exploding';
        $result['SOULPOINTS'] = 'soulPointRegen';
        $result['STAMINA'] = 'sprint';
        $result['STAMINA_REGEN'] = 'sprintRegen';
        $result['JUMP_HEIGHT'] = 'rawJumpHeight';
        $result['XPBONUS'] = 'xpBonus';
        $result['LOOTBONUS'] = 'lootBonus';
        $result['EMERALDSTEALING'] = 'stealing';
        $result['EARTHDAMAGEBONUS'] = 'earthDamage';
        $result['THUNDERDAMAGEBONUS'] = 'thunderDamage';
        $result['WATERDAMAGEBONUS'] = 'waterDamage';
        $result['FIREDAMAGEBONUS'] = 'fireDamage';
        $result['AIRDAMAGEBONUS'] = 'airDamage';
        $result['EARTHDEFENSE'] = 'earthDefence';
        $result['THUNDERDEFENSE'] = 'thunderDefence';
        $result['WATERDEFENSE'] = 'waterDefence';
        $result['FIREDEFENSE'] = 'fireDefence';
        $result['AIRDEFENSE'] = 'airDefence';
        $result['SPELLTHUNDERDAMAGEBONUSRAW'] = 'rawThunderSpellDamage';
        $result['SPELLFIREDAMAGEBONUSRAW'] = 'rawFireSpellDamage';
        $result['SPELLWATERDAMAGEBONUSRAW'] = 'rawWaterSpellDamage';
        $result['SPELLAIRDAMAGEBONUSRAW'] = 'rawAirSpellDamage';
        $result['SPELLEARTHDAMAGEBONUSRAW'] = 'rawEarthSpellDamage';

        return $result;
    }

    public static function getMajorIdentifications(): array {
        $result = [];

        foreach (MajorIdentifications::cases() as $enum) {
            $result[$enum->name] = [
                'name' => $enum->displayName(),
                'description' => $enum->description()
            ];
        }

        return $result;
    }

    private static function translateStatusName(string $raw): ?string
    {
        return match ($raw) {
            'spellCostPct1', 'spellCost1Pct' => '1stSpellCost',
            'spellCostPct2', 'spellCost2Pct' => '2ndSpellCost',
            'spellCostPct3', 'spellCost3Pct' => '3rdSpellCost',
            'spellCostPct4', 'spellCost4Pct' => '4thSpellCost',
            'spellCostRaw1' => 'raw1stSpellCost',
            'spellCostRaw2' => 'raw2ndSpellCost',
            'spellCostRaw3' => 'raw3rdSpellCost',
            'spellCostRaw4' => 'raw4thSpellCost',
            'spellDamageBonusRaw' => 'rawSpellDamage',
            'mainAttackDamageBonusRaw' => 'rawMainAttackDamage',
            'mainAttackDamageBonus' => 'mainAttackDamage',
            'healthRegenRaw' => 'rawHealthRegen',
            'healthBonus' => 'rawHealth',
            'speed' => 'walkSpeed',
            'soulPoints' => 'soulPointRegen',
            'emeraldStealing' => 'stealing',
            'strengthPoints' => 'rawStrength',
            'dexterityPoints' => 'rawDexterity',
            'intelligencePoints' => 'rawIntelligence',
            'defensePoints' => 'rawDefence',
            'agilityPoints' => 'rawAgility',
            'bonusEarthDamage', 'earthDamageBonus' => 'earthDamage',
            'bonusThunderDamage', 'thunderDamageBonus' => 'thunderDamage',
            'bonusWaterDamage', 'waterDamageBonus' => 'waterDamage',
            'bonusFireDamage', 'fireDamageBonus' => 'fireDamage',
            'bonusAirDamage', 'airDamageBonus' => 'airDamage',
            'bonusEarthDefense', 'earthDefenseBonus' => 'earthDefence',
            'bonusThunderDefense', 'thunderDefenseBonus' => 'thunderDefence',
            'bonusWaterDefense', 'waterDefenseBonus' => 'waterDefence',
            'bonusFireDefense', 'fireDefenseBonus' => 'fireDefence',
            'bonusAirDefense', 'airDefenseBonus' => 'airDefence',
            'jumpHeight' => 'rawJumpHeight',
            'spellElementalDamageBonusRaw' => 'rawSpellDamage',
            'spellThunderDamageBonusRaw' => 'rawThunderSpellDamage',
            'spellFireDamageBonusRaw' => 'rawFireSpellDamage',
            'spellAirDamageBonusRaw' => 'rawAirSpellDamage',
            'spellEarthDamageBonusRaw' => 'rawEarthSpellDamage',
            'spellWaterDamageBonusRaw' => 'rawWaterSpellDamage',
            'attackSpeedBonus' => 'attackSpeed',
            //same ones
            'gatherXpBonus' => 'gatherXpBonus',
            'spellDamageBonus' => 'spellDamage',
            'healthRegen' => 'healthRegen',
            'poison' => 'poison',
            'lifeSteal' => 'lifeSteal',
            'manaRegen' => 'manaRegen',
            'exploding' => 'exploding',
            'sprint' => 'sprint',
            'sprintRegen' => 'sprintRegen',
            'lootBonus' => 'lootBonus',
            'lootQuality' => 'lootQuality',
            'gatherSpeed' => 'gatherSpeed',
            'xpBonus' => 'xpBonus',
            'manaSteal' => 'manaSteal',
            'thorns' => 'thorns',
            'reflection' => 'reflection',
            default => null,
        };
    }

    public function convertMaterial($material) {
        $item = str($material);
        if ($item->startsWith('minecraft:')) {
            return [
                'id' => $item,
                'name' => $item
            ];
        }

        if ($item->contains(':')) {
            [$id, $durability] = $item->explode(':');
        } else {
            $id = $material;
            $durability = 0;
        }

        $item = $this->prismarineJSData->get($id);
        if ($item === null) {
            return null;
        }

        return [
            'name' => 'minecraft:' . $item?->name ?? 'unknown',
            'damage' => $durability,
        ];
    }

    public function getItemTranslations($materials)
    {
        $itemDB = $this->prismarineJSData;

        $materials = collect($materials)->flatten()->unique();

        return $materials->mapWithKeys(function ($item, $key) use ($itemDB) {
            $item = str($item);
            if ($item->startsWith('minecraft:')) {
                return [$item->toString() => [
                    'name' => $item,
                    'damage' => 0,
                ]];
            }

            [$id, $durability] = $item->split('/:/');

            $i = $itemDB->get($id);

            return [$item->toString() => [
                'name' => 'minecraft:' . $i?->name ?? 'unknown',
                'damage' => $durability,
            ]];
        });
    }
}
