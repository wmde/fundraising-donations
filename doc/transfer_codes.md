# Bank Transfer Codes

Bank transfer codes are alphanumeric strings of 9 characters that uniquely identify a donation. 
They can be split with dashes or other punctuation characters for better readability, but before validating a code, these 
characters have to be removed.

Bank transfer codes have three distinct parts: The prefix (2 chars), the ID (6 chars), and the checksum (1 char).

Transfer codes use a custom, 19 character, character set:

	ACDEFKLMNPRTWXYZ349

This set of 19 characters was chosen because it does not contain characters that have strong visual resemblance to each other 
(like `0` and `O` or `S` and `5`).

The codes start with the prefixes `XW` and `XR`. The prefixes signify internal donation types.

## Verify a code

0. Convert the transfer code to upper case

   Examples:
     - `XW-A3d-EFT-Z` => `XW-A3D-EFT-Z`

1. Strip all characters not in the character set

   Examples:
     - `XW-A3D-EFT-Z` => `XWA3DEFTZ`

2. Make sure that the string code is exactly 9 characters long

   Examples:
     - `XWA3DEFTZ` => OK

3. Calculate the MD5 sum of the first 8 characters

   Examples:
     - `XWA3DEFT` => `fa4d9a9ec0894cd1b65de63a5e705f05`

4. Calculate the decimal digit sum of the hexadecimal MD5 

   Examples:
     - the decimal digit sum of `fa4d9a9ec0894cd1b65de63a5e705f05` becomes `262`
     - the decimal digit sum of `aa` becomes `20` (10+10),
     - the decimal digit sum of `12FE` becomes `32` (1+2+15+14)

5. Calculate the modulo 19 of the decimal digit sum

   Examples:
     - `262 % 19` => `15`

6. In the character set, get the character at the index equaling the number from step 5 
   
   Examples: 
     - 0 => `A`
     - 1 => `B`
     - 15 => `Z`

7. Compare the last character of the transfer code to the character from step 6

   Examples: `Z` = `Z` => OK
