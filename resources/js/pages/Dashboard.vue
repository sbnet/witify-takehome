<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, ClipboardList } from '@lucide/vue';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const repositoryUrl = 'https://github.com/witify/witify-takehome';
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Welcome!</h1>
            <p class="text-sm text-muted-foreground">
                Start by reading the exercise instructions, then explore the
                orders and inventory pages.
            </p>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
            <a
                :href="`${repositoryUrl}/blob/main/EXERCISE.md`"
                target="_blank"
                rel="noopener noreferrer"
            >
                <Card class="h-full transition-colors hover:bg-muted/50">
                    <CardHeader>
                        <ClipboardList class="size-5 text-muted-foreground" />
                        <CardTitle>Exercise instructions</CardTitle>
                        <CardDescription>
                            EXERCISE.md — what to build, the functional rules
                            and the deliverables.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </a>

            <a
                :href="`${repositoryUrl}#readme`"
                target="_blank"
                rel="noopener noreferrer"
            >
                <Card class="h-full transition-colors hover:bg-muted/50">
                    <CardHeader>
                        <BookOpen class="size-5 text-muted-foreground" />
                        <CardTitle>Project README</CardTitle>
                        <CardDescription>
                            Setup, demo account, project tour and useful
                            commands.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </a>
        </div>
    </div>
</template>
