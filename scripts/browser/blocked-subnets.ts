/** IP ranges that must never be used as browser destinations. */
export enum BlockedIpv4Subnet {
  CurrentNetwork = '0.0.0.0/8',
  Private10 = '10.0.0.0/8',
  SharedAddressSpace = '100.64.0.0/10',
  Loopback = '127.0.0.0/8',
  LinkLocal = '169.254.0.0/16',
  Private172 = '172.16.0.0/12',
  ProtocolAssignments = '192.0.0.0/24',
  Documentation1 = '192.0.2.0/24',
  Private192 = '192.168.0.0/16',
  Benchmarking = '198.18.0.0/15',
  Documentation2 = '198.51.100.0/24',
  Documentation3 = '203.0.113.0/24',
  Multicast = '224.0.0.0/4',
  Reserved = '240.0.0.0/4',
}

export enum BlockedIpv6Subnet {
  Unspecified = '::/128',
  Loopback = '::1/128',
  UniqueLocal = 'fc00::/7',
  LinkLocal = 'fe80::/10',
  Multicast = 'ff00::/8',
  Documentation = '2001:db8::/32',
}
