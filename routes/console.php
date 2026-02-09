<?php

use App\Console\Commands\AddAdsBalancesToGoogleSheetCommand;
use App\Console\Commands\CsdProjectsRefreshSpendsCommand;
use App\Console\Commands\FacebookInsightsBigQuery;
use App\Console\Commands\GoogleAdsToBigQuery;
use App\Console\Commands\Notifications\FacebookTokenExpiration;
use App\Console\Commands\Notifications\FbAdAccountsBalances;
use App\Console\Commands\Notifications\UpWorkJobsInSlack;
use App\Console\Commands\PipeDriveToBigQuery\Deals;
use App\Console\Commands\PipeDriveToBigQuery\UpdateCustomFieldsDealsValues;
use App\Console\Commands\PipeDriveToBigQuery\UpdateDealsObjects;
use App\Console\Commands\PipeDriveToBigQuery\UpdateFieldsAndPipelines;
use App\Console\Commands\TikTokAdToBigQuery;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PruneCommand::class)->daily();

Schedule::command(FacebookInsightsBigQuery::class)->dailyAt('1');
Schedule::command(TikTokAdToBigQuery::class)->dailyAt('2');
Schedule::command(GoogleAdsToBigQuery::class)->dailyAt('3');

Schedule::command(CsdProjectsRefreshSpendsCommand::class)->dailyAt('4');

Schedule::command(FbAdAccountsBalances::class)->everyFourHours();
Schedule::command(UpWorkJobsInSlack::class)->hourlyAt('2,12,22,32,42,52');
Schedule::command(FacebookTokenExpiration::class)->dailyAt('10:01');
//Schedule::command(BSGBalance::class)->dailyAt('10:02');

Schedule::command(AddAdsBalancesToGoogleSheetCommand::class)->dailyAt('10:03');



// PipeDrive
Schedule::command(UpdateDealsObjects::class)->dailyAt('02:05');
Schedule::command(UpdateFieldsAndPipelines::class)->dailyAt('02:35');
Schedule::command(UpdateCustomFieldsDealsValues::class)->dailyAt('03');
Schedule::command(Deals::class)->hourlyAt('2');
