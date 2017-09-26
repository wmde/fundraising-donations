# Bank Transfer Codes

Bank transfer codes are alphanumeric strings of 9 characters that uniquely identify a donation. 
They can be split with dashes or other punctuation characters for better readability, but before validating a code, the dashes have to be removed.
Bank transfer codes have three distinct parts: The prefix (2 chars), the ID (6 chars) and the checksum (1 char).
Internally, bank transfer codes are integer numbers, but for making them shorter they are encoded in a custom 19 character set:

	ACDEFKLMNPRTWXYZ349

This set of 19 characters was chosen because it does not contain that are similar to each other (like `0` and `O` or `S` and `5`).

The codes start with the prefixes `XW` and `XR`. The prefixes signify internal donation types.

## Verify a code

1. Strip all characters not in the character set from the transfer code.
2. Make sure that the remaining string code is exactly 9 characters long.
3. Split stripped transfer code into prefix, ID and checksum parts.
4. Convert parts into integers:
	1. Map each character to a character from the base19 character set (see below)
	2. Convert base19 string to base10 integer.
5. Concatenate base10 prefix and base10 ID.
6. Calculate the ISO 71064 MOD 11-2 checksum from the concatenated string.
7. The calculated checksum must be equal to the base10 checksum from step 4.

### Base19 to character set map

| Base19  | Charset  |
|---|---|
| 0  | A |
| 1  | C |
| 2  | D |
| 3  | E |
| 4  | F |
| 5  | K |
| 6  | L |
| 7  | M |
| 8  | N |
| 9  | P |
| A  | R |
| B  | T |
| C  | W |
| D  | X |
| E  | Y |
| F  | Z |
| G  | 3 |
| H  | 4 |
| I  | 9 | 

### Example: Verifying `X-W-MKZ-4C3-L` 
1. The code stripped of all dashes is `XWMKZ4C3L`. 
2. It's 9 characters long, so the first criterion for validity is given.
3. Prefix is `XW` ID is `MKZ4C3` and checksum is `L`.
4. Convert step:
	1. base19 prefix is `DC` ID is `75FG1G` and checksum is `6`.
	2. base10 prefix is 259 ID is 18092994 and checksum is 6.
5. Concatenated number for calculating the checksum is `618092994`
6. The MOD 11-2 Checksum for `618092994` is 6.
7. The code is valid, 6 equals 6.

## How the codes are generated

1. Create ID as a random number between 5153632 and 47045880. These number guarantee a 6-digit number in base19.
2. Convert the prefix to an integer using the the character set map and base19 conversion. 259 for `XW` and 257 for `XR`.
3. Concatenate integer prefix and ID and calculate the ISO 71064 MOD 11-2 checksum from it. If the checksum is `X`, store it as `10` instead.
4. Convert ID and checksum:
	2. Convert base10 integer to base19 string.
	1. Map each from the base19 character set to a character from our custom character set (see above).
5. Concat encoded prefix, ID and Checksum with dashes.