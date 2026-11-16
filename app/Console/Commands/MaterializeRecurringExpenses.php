<?php

namespace App\Console\Commands;

use App\Services\Finance\ExpenseService;
use Illuminate\Console\Command;

/** Creates the upcoming occurrences of open-ended recurring expenses (also done on page loads). */
class MaterializeRecurringExpenses extends Command
{
    protected $signature = 'expenses:recurring';

    protected $description = 'Create the upcoming occurrences of recurring expenses that have no set number of times';

    public function handle(ExpenseService $expenses): int
    {
        $created = $expenses->materialize();
        $this->components->info(trans_choice('{0} Nothing to create.|{1} :count occurrence created.|[2,*] :count occurrences created.', $created));

        return self::SUCCESS;
    }
}
