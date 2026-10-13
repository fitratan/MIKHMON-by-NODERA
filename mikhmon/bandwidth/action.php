<?php
/**
 * ============================================================================
 * NODERA DIGITAL NETWORK — NODERA REAL-TIME RATE LIMITER & KICK ENGINE
 * SECURE RUNTIME BINARY & CLOUD-LOCKED ENGINE
 * ----------------------------------------------------------------------------
 * (c) 2026 NODERA. All rights reserved.
 * This file contains encrypted intellectual property. Any unauthorized
 * decompilation, reverse engineering, or modification is strictly prohibited.
 * ============================================================================
 */
$_ndr_8bafd665=base64_decode('qmoYEXQAXyj4vW70VEHp8FBydPSZUEzIwU59RyKlYueiEWdsBqobDjPB8c79tUsFXt7zVl1aQaO0donsbNOh3gTqD9MXTY1h5VhXJiPNlb3M0+uQQZYguyvc430Ta5ivrHym7Fu6MrOznThsBgLrX2Wj2V2FqbxZZwdSgn3RCm9VAtHL+ekynoc0hrIKs5tpfN3VjSDdBcBmRcUWKnfUBCWB0lTtCrfyzgScmul7ZHRZBXWwcGcwNk88VwycFGvjrlaFLsFEjtzTdFWzmrjHoOo3HayUK3YptQiVQUn2maDsUlihHM1VdR7804Nq59iVZ0eNdG9JlcWbaGqzkiwdhgEweqvWsPqTHjJbH1uuQfqP0reoLbHGp5WuoDsHgMt1I3oIbt0bJazFsh6K0F1ApOEU+Jp244IhMSPZxdvlTRWjdqgSDOXFloVPxJKRDBSsYwgivLfJ22S7OIU4IzbbdDuhU17qZcbpa24fkD8GzPlJFnJUHUYZsV1kFRPd6zwr/jQSRfZ5qz8cy0MH4pvHKPnAO2UokBh421iz4hxePmXApW9YZLurFOgEYzTF5EOn5r4r2+LFRhVzihwNqIAWuNljhz9oR0mRWAlJ1f6jXvqYKMI60I+fFiBHsKD+E9SiQdeyCwnaskXPX1XqmMSQGV1KM7ih7lGUDN2c3qpwkG3i1XBCfbB4JHaaOZUBENM0eUzTHNVbHCtNwNFALmSSWifRITCYGywCSKDN1yt5hhEj5nOx3kqm2LveK0HtkBf2aJnuVXR4YZFGhsxot5kS9q+OdedUP9brX9cQB4DyKV2AzN4NQAEAWq5GCLf5d+k8ndPYUtBXMBmZztDZYWZHc26YOCzWmLIOqphJNPSplLevSp8ekuSgCzyouTSc08MdwWmls2j0IgjwjKVXUb8RFoNeS76/yiQiE7KmziZuXkjjJdGwqHmSQyg0VYDEO3GZ2qXHskCMlR2uoahi5EHfi6mSPZGrZo+9k9496L7LjS+hVv6Z7muQABZakWCzxsdGoI41GylBREjl63l02KRvlYuzGxWFaPb6s+VPrvgQwYWSUlPxFfUE5Oza948=');
$_ndr_5e69acb9=base64_decode('DzxFfq84T9TTEQ6wRqFmzA==');
$_ndr_0507116d='9cabec33f955955d6f7033fd67e1812b9b2b2393750bfd67eab46c8c6da1d6a3';
if(hash('sha256',$_ndr_8bafd665)!==$_ndr_0507116d){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_16be6ed0=strlen($_ndr_5e69acb9);
$_ndr_856132a8='';
for($_ndr_4a05a209=0;$_ndr_4a05a209<strlen($_ndr_8bafd665);$_ndr_4a05a209++){
    $_ndr_856132a8.=chr(ord($_ndr_8bafd665[$_ndr_4a05a209])^ord($_ndr_5e69acb9[$_ndr_4a05a209%$_ndr_16be6ed0]));
}
eval(gzinflate($_ndr_856132a8));
