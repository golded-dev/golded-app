<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $dataset = Dataset::create(['name' => 'Goldware BBS', 'source_type' => 'msg']);

        $areas = [
            ['code' => 'GOLDED',   'name' => 'Goldware Support',  'echoid' => 'GOLDED',   'msgs' => 142, 'unread' => 12],
            ['code' => 'FIDONET',  'name' => 'FidoNet.General',   'echoid' => 'FIDONET',  'msgs' => 89,  'unread' => 5],
            ['code' => 'NETMAIL',  'name' => 'NetMail',           'echoid' => 'NETMAIL',  'msgs' => 12,  'unread' => 2],
            ['code' => 'DK.SNAK',  'name' => 'DK.Snak',          'echoid' => 'DK.SNAK',  'msgs' => 67,  'unread' => 0],
            ['code' => 'OS2.GEN',  'name' => 'OS2.General',      'echoid' => 'OS2.GEN',  'msgs' => 34,  'unread' => 3],
            ['code' => 'THE_SAFE', 'name' => 'THE_SAFE',         'echoid' => 'THE_SAFE', 'msgs' => 8,   'unread' => 0],
        ];

        foreach ($areas as $sort => $data) {
            $area = Area::create([
                'dataset_id' => $dataset->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'echoid' => $data['echoid'],
                'sort_order' => $sort + 1,
                'message_count' => $data['msgs'],
                'unread_count' => $data['unread'],
            ]);

            // Seed some messages for the NetMail area
            if ($data['code'] === 'NETMAIL') {
                $this->seedNetmailMessages($dataset, $area);
            }
        }
    }

    private function seedNetmailMessages(Dataset $dataset, Area $area): void
    {
        $base = Carbon::create(1994, 3, 12);

        $messages = [
            [1, 'Bjarne Hansen',  '2:236/12',  'Odinn Sorensen', 'Re: GoldED 3.0 beta',         "Just tried the beta. Looks excellent so far!\r\n\r\n--- GoldED 3.0.1 Beta\r\n * Origin: Haslev BBS (2:236/12)", true,  '+0'],
            [2, 'Uffe Sorensen',  '2:236/55',  'Odinn Sorensen', 'Nodelist update',              "The nodelist for week 10 is now available from the hub.\r\n\r\n--- GoldED 3.0.1\r\n * Origin: Sorensen Net (2:236/55)", false, '+1'],
            [3, 'Odinn Sorensen', '2:236/77',  'Lars Jensen',    'Re: GoldED keybindings',       "Right/Enter both work for AREAselect. Check your GOLDKEYS.CFG.\r\nThe default binding has both mapped:\r\n\r\n  Right -> AREAselect\r\n  Enter -> AREAselect\r\n\r\nLet me know if you're still stuck.\r\n\r\n--- GoldED 3.0.1 Beta 3\r\n * Origin: Goldware BBS, Haslev (2:236/77)", false, '+0'],
            [4, 'Lars Jensen',    '2:236/105', 'Odinn Sorensen', 'Re: GoldED keybindings',       "I noticed the key binding for AREA select seems odd —\r\npressing Right should open the area but it doesn't?\r\n\r\n--- GoldED 2.5\r\n * Origin: Jensen Net (2:236/105)", false, '+0'],
            [5, 'Peter Froerup',  '2:236/88',  'Odinn Sorensen', 'Re: GoldED keybindings',       "Same issue here. Right arrow doesn't work for me either.\r\n\r\n--- GoldED 2.5\r\n * Origin: Froerup BBS (2:236/88)", false, '+0'],
            [6, 'Thomas Nielsen', '2:236/33',  'Odinn Sorensen', 'New beta available?',           "Is there a new beta in the pipeline? Last one was excellent.\r\n\r\n--- GoldED 2.5\r\n * Origin: Nielsen Net (2:236/33)", false, '+1'],
        ];

        foreach ($messages as [$msgno, $from, $fromAddr, $to, $subj, $body, $isRead, $offset]) {
            Message::create([
                'dataset_id' => $dataset->id,
                'area_id' => $area->id,
                'msgno' => $msgno,
                'subject' => $subj,
                'from_name' => $from,
                'from_address' => $fromAddr,
                'to_name' => $to,
                'body_text' => $body,
                'attributes_raw' => 0,
                'posted_at' => $base->copy()->addDays($msgno - 1),
                'is_read' => $isRead,
            ]);
        }
    }
}
