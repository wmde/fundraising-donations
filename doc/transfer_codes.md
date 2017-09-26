# Bank Transfer Codes

Bank transfer codes are alphanumeric strings of 9 characters that uniquely identify a donation. 
They can be split with dashes or other punctuation characters for better readability, but before validating a code, the dashes have to be removed.
Bank transfer codes have three distinct parts: The prefix (2 chars), the ID (6 chars) and the checksum (1 char).
Transfer codes use a custom 19 character set:

	ACDEFKLMNPRTWXYZ349

This set of 19 characters was chosen because it does not contain that are similar to each other (like `0` and `O` or `S` and `5`).

The codes start with the prefixes `XW` and `XR`. The prefixes signify internal donation types.

## Verify a code

1. Strip all characters not in the character set from the transfer code.
2. Make sure that the remaining string code is exactly 9 characters long.´
3. Calculate the MD5 sum of the first 8 characters.
4. Calculcate the decimal digit sum of the hexadecimal MD5 string. Example: The decimal digit sum of hex `aa` becomes 20 (10+10), the decimal digit sum of `12FE` becomes 32 (1+2+15+14)  
5. Calculcate the modulo 19 of the decimal digit sum. 
6. Get the character at the index of the number of step 5 from the character set. Examples: 0=`A`, 1 => `B`, 17 => `4` 18 => `9`
7. Compare the last character of the transfer code to the character of step 6.  
