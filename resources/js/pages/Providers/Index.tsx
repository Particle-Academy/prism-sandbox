import { Button, Card } from '@particle-academy/react-fancy';
import { useState } from 'react';
import { PortBadges, ResearchStatus, SetupLayout, type ProviderGuide } from './shared';

export default function Index({ providers, version }: { providers: ProviderGuide[]; version: string }) {
    const [capability, setCapability] = useState('all');
    return <SetupLayout version={version}>
        <p className="k-mono mb-3">Provider setup</p>
        <h1 className="text-4xl font-bold tracking-tight">Connect your own application</h1>
        <p className="mt-4 max-w-2xl text-[var(--k-ink-2)]">Create credentials at your provider, configure them on your own machine, and make a small local verification request. This site never asks for, stores or tests your credentials.</p>
        <div className="my-8 flex flex-wrap gap-2" aria-label="Filter by capability">{['all', 'text', 'images', 'embeddings', 'audio', 'fim'].map(value => <Button key={value} type="button" active={capability === value} aria-pressed={capability === value} onClick={() => setCapability(value)}>{value === 'all' ? 'All providers' : value}</Button>)}</div>
        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">{providers.filter(provider => capability === 'all' || provider.capabilities.includes(capability)).map(provider => <Card key={provider.id} className="min-w-0" data-provider-id={provider.id}><Card.Header><h2 className="text-xl font-semibold">{provider.name}</h2></Card.Header><Card.Body><PortBadges provider={provider} /><p className="mt-4 text-sm text-[var(--k-ink-2)]">{provider.billing}</p><ResearchStatus provider={provider} /></Card.Body><Card.Footer><Button href={`/providers/setup/${provider.id}`} variant="ghost">Set up {provider.name}</Button></Card.Footer></Card>)}</div>
    </SetupLayout>;
}
