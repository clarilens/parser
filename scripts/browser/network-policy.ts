import {lookup} from 'node:dns/promises';
import {BlockList, isIP} from 'node:net';
import {BlockedIpv4Subnet, BlockedIpv6Subnet} from './blocked-subnets.js';

const blocked = new BlockList();

function addSubnets(subnets: readonly string[], family: 'ipv4' | 'ipv6'): void {
    for (const subnet of subnets) {
        const separator = subnet.lastIndexOf('/');
        blocked.addSubnet(subnet.slice(0, separator), Number(subnet.slice(separator + 1)), family);
    }
}

addSubnets(Object.values(BlockedIpv4Subnet), 'ipv4');
addSubnets(Object.values(BlockedIpv6Subnet), 'ipv6');

export async function pinAllowedHosts(hosts: readonly string[]): Promise<Map<string, string>> {
    const pinned = new Map<string, string>();
    for (const host of hosts) {
        const family = isIP(host);
        const addresses = family ? [{address: host, family}] : await lookup(host, {all: true});
        if (addresses.length === 0 || addresses.some(({address, family}) =>
            address.toLowerCase().startsWith('::ffff:')
            || blocked.check(address, family === 4 ? 'ipv4' : 'ipv6'))) {
            throw new Error('Allowed host has no public address');
        }
        pinned.set(host, addresses.find(address => address.family === 4)?.address ?? addresses[0]!.address);
    }
    return pinned;
}

export function hostResolverRules(pinned: ReadonlyMap<string, string>): string {
    return [...pinned].map(([host, address]) => `MAP ${host} ${address}`)
        .concat('MAP * ~NOTFOUND').join(', ');
}
