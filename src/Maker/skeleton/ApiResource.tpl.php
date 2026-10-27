#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [self::GROUP_LIST]],
            security: "is_granted('VIEW', '<?= $resource->snake ?>')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('CREATE', '<?= $resource->snake ?>')"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_EDIT]],
    provider: <?= $provider_class ?>::class,
    paginationClientItemsPerPage: true,
)]
