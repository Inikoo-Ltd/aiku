<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Oct 2025 12:18:47 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Catalogue\Collection\AttachCollectionToModel;
use App\Actions\Catalogue\Collection\DeleteCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Collection\StoreCollectionWebpage;
use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterCollection\AttachMasterCollectionToModel;
use App\Actions\Masters\MasterCollection\DeleteMasterCollection;
use App\Actions\Masters\MasterCollection\StoreMasterCollection;
use App\Actions\Masters\MasterCollection\UpdateMasterCollection;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneCollections
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop|Shop $fromShop, MasterShop|Shop $shop, $deleteMissing = false): void
    {
        $this->cloneCollections($fromShop, $shop);

        if ($deleteMissing) {
            $this->deleteCollectionsNotFoundInMaster($fromShop, $shop);
        }

        // $this->attachFamiliesToCollections($fromShop, $shop);
        // $this->attachProductsToCollections($fromShop, $shop);
        // $this->attachCollectionsToCollections($fromShop, $shop);
    }

    public function deleteCollectionsNotFoundInMaster(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        if ($shop instanceof Shop) {
            $collections = $shop->shopCollections;
        } else {
            $collections = $shop->masterShopMasterCollections;
        }


        if ($fromShop instanceof Shop) {
            $codes = $fromShop->shopCollections()->pluck('code');
        } else {
            $codes = $fromShop->masterShopMasterCollections()->pluck('code');
        }



        foreach ($collections as $collection) {
            if (!in_array($collection->code, $codes->toArray())) {
                if ($collection instanceof MasterCollection) {
                   print "Deleting master collection  ".$collection->id." ".$collection->name."\n";;
                    DeleteMasterCollection::run($collection, true);
                } else {
                    print "Deleting collection  ".$collection->id." ".$collection->name."\n";;
                    DeleteCollection::run($collection, true);
                }
            }
        }
    }

    public function cloneCollections(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        /** @var Collection|MasterCollection $fromCollection */

        if ($fromShop instanceof MasterShop) {
            foreach ($fromShop->masterShopMasterCollections as $fromCollection) {
                if ($shop instanceof Shop) {
                    $this->upsertCollection($shop, $fromCollection);
                } else {
                    $this->upsertMasterCollection($shop, $fromCollection);
                }
            }
        } else {
            foreach ($fromShop->shopCollections as $fromCollection) {
                if ($shop instanceof Shop) {
                    $this->upsertCollection($shop, $fromCollection);
                } else {
                    $this->upsertMasterCollection($shop, $fromCollection);
                }
            }
        }
    }


    /**
     * @throws \Throwable
     */
    public function getParents(Collection|MasterCollection $collection, MasterShop|Shop $target): array
    {
        $parents = [];

        if ($collection instanceof MasterCollection) {
            foreach ($collection->parentMasterDepartments as $parentMasterDepartment) {
                if ($target instanceof Shop) {
                    $department = CloneCatalogueStructure::make()->upsertDepartment($target, $parentMasterDepartment);
                } else {
                    $department = CloneCatalogueStructure::make()->upsertMasterDepartment($target, $parentMasterDepartment);
                }
                if ($department) {
                    $parents[] = $department;
                }
            }

            foreach ($collection->parentMasterSubDepartments as $parentSubMasterDepartment) {
                if ($target instanceof Shop) {
                    $department    = CloneCatalogueStructure::make()->upsertDepartment($target, $parentSubMasterDepartment->parent);
                    $subdepartment = CloneCatalogueStructure::make()->upsertSubDepartment($department, $parentSubMasterDepartment);
                } else {
                    $department    = CloneCatalogueStructure::make()->upsertMasterDepartment($target, $parentSubMasterDepartment->parent);
                    $subdepartment = CloneCatalogueStructure::make()->upsertMasterSubDepartment($department, $parentSubMasterDepartment);
                }

                $parents[] = $subdepartment;
            }
        } else {
            foreach ($collection->parentDepartments as $parentDepartment) {
                if ($target instanceof Shop) {
                    $department = CloneCatalogueStructure::make()->upsertDepartment($target, $parentDepartment);
                } else {
                    $department = CloneCatalogueStructure::make()->upsertMasterDepartment($target, $parentDepartment);
                }
                if ($department) {
                    $parents[] = $department;
                }
            }

            foreach ($collection->parentSubDepartments as $parentSubDepartment) {
                if ($target instanceof Shop) {
                    $department    = CloneCatalogueStructure::make()->upsertDepartment($target, $parentSubDepartment->parent);
                    $subdepartment = CloneCatalogueStructure::make()->upsertSubDepartment($department, $parentSubDepartment);
                } else {
                    $department    = CloneCatalogueStructure::make()->upsertMasterDepartment($target, $parentSubDepartment->parent);
                    $subdepartment = CloneCatalogueStructure::make()->upsertMasterSubDepartment($department, $parentSubDepartment);
                }

                $parents[] = $subdepartment;
            }
        }

        return $parents;
    }

    public function upsertMasterCollection(MasterShop $masterShop, Collection|MasterCollection $collection): MasterCollection|null
    {
        $code = $collection->code;

        $foundMasterCollectionData = DB::table('master_collections')
            ->where('master_shop_id', $masterShop->id)
            ->where('deleted_at', null)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();

        $english = Language::where('code', 'en')->first();


        $parents = $this->getParents($collection, $masterShop);

        if (!$foundMasterCollectionData) {
            if ($collection instanceof Collection) {
                $originalLanguage = $collection->shop->language;
            } else {
                $originalLanguage = $english;
            }


            $name = $collection->name;
            $name = Translate::run($name, $originalLanguage, $english);

            $description = $collection->description;
            $description = Translate::run($description, $originalLanguage, $english);




            $foundMasterCollection = StoreMasterCollection::make()->action(
                parent: $masterShop,
                modelData: [
                    'code'        => $collection->code,
                    'name'        => $name,
                    'description' => $description,
                ],
                createChildren: false
            );


            print "Created master collection  $foundMasterCollection->id ".$foundMasterCollection->name."\n";
        } else {
            $foundMasterCollection = MasterCollection::find($foundMasterCollectionData->id);

            $dataToUpdate = [
                'code' => $collection->code,
            ];


            $foundMasterCollection = UpdateMasterCollection::make()->action(
                $foundMasterCollection,
                $dataToUpdate
            );
        }


        foreach ($parents as $parent) {
            AttachMasterCollectionToModel::run($parent, $foundMasterCollection);
        }


        UpdateCollection::run($collection, ['master_collection_id' => $foundMasterCollection?->id]);


        return $foundMasterCollection;
    }

    public function upsertCollection(Shop $shop, Collection|MasterCollection $collection): ?Collection
    {
        $code = $collection->code;

        $foundCollectionData = DB::table('collections')
            ->where('shop_id', $shop->id)
            ->where('deleted_at', null)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();

        if ($collection instanceof MasterCollection) {
            $fromLanguage = Language::where('code', 'en')->first();
        } else {
            $fromLanguage = $collection->shop->language;
        }
        $toLanguage = $shop->language;

        $parents = $this->getParents($collection, $shop);


        if (!$foundCollectionData) {
            $descriptionFields = [
                'name'        => Translate::run($collection->name, $fromLanguage, $toLanguage),
                'description' => Translate::run($collection->description, $fromLanguage, $toLanguage),
            ];

            $foundCollection = StoreCollection::make()->action(
                $shop,
                array_merge(
                    $descriptionFields,
                    [
                        'code' => $collection->code,
                    ]
                )
            );
        } else {
            $foundCollection = Collection::find($foundCollectionData->id);

            if ($foundCollection) {
                $descriptionFields = [];
                if ($foundCollection->name == '' && $collection->name) {
                    $descriptionFields['name']             = Translate::run($collection->name, $fromLanguage, $toLanguage);
                    $descriptionFields['is_name_reviewed'] = false;
                }
                if ($foundCollection->description == '' && $collection->description) {
                    $descriptionFields['description']             = Translate::run($collection->description, $fromLanguage, $toLanguage);
                    $descriptionFields['is_description_reviewed'] = false;
                }


                $dataToUpdate = array_merge(
                    $descriptionFields,
                    [
                        'code' => $collection->code,
                    ]
                );

                $foundCollection = UpdateCollection::make()->action(
                    $foundCollection,
                    $dataToUpdate
                );
            }
        }
        if ($foundCollection && !$foundCollection->webpage) {
            try {
                $webpage = StoreCollectionWebpage::run($foundCollection);
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'Published after cloning',
                    ]
                );
            } catch (\Throwable $e) {
                print $foundCollection->slug.' '.$e->getMessage()."\n";
            }
        }

        if ($collection instanceof MasterCollection) {
            $foundCollection->update([
                'master_collection_id' => $collection->id
            ]);
        }

        foreach ($parents as $parent) {



            AttachCollectionToModel::run($parent, $foundCollection);
        }


        return $foundCollection;
    }


    public function getCommandSignature(): string
    {
        return 'collections:clone {from_type} {from} {to_type} {to} {--delete-missing : Delete collections not found in source shop}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('to_type') == 'shop') {
            $toShop = Shop::where('slug', $command->argument('to'))->firstOrFail();
        } else {
            $toShop = MasterShop::where('slug', $command->argument('to'))->firstOrFail();
        }


        if ($command->argument('from_type') == 'shop') {
            $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();
        } else {
            $fromShop = MasterShop::where('slug', $command->argument('from'))->firstOrFail();
        }

        $deleteMissing = $command->option('delete-missing');
        $this->handle($fromShop, $toShop, $deleteMissing);

        return 0;
    }


}
